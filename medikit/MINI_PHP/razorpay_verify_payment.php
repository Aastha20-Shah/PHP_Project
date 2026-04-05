<?php

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/billing_helpers.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/razorpay_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['patient_id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];

$bill_id = (int)($_POST['bill_id'] ?? 0);
$order_id = trim((string)($_POST['razorpay_order_id'] ?? ''));
$payment_id = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));

if ($bill_id <= 0 || $order_id === '' || $payment_id === '' || $signature === '') {
  echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
  exit;
}

try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Billing is not available right now.']);
  exit;
}

// Ensure this order is mapped to this bill and this patient.
$pstmt = $conn->prepare('SELECT id, bill_id, booking_id, amount, currency, status FROM clinic_bill_payments WHERE razorpay_order_id = ? AND bill_id = ? AND patient_id = ? LIMIT 1');
if (!$pstmt) {
  echo json_encode(['success' => false, 'message' => 'Failed to verify payment.']);
  exit;
}

$pstmt->bind_param('sii', $order_id, $bill_id, $patient_id);
$pstmt->execute();
$pres = $pstmt->get_result();
$paymentRow = $pres ? $pres->fetch_assoc() : null;
$pstmt->close();

if (!$paymentRow) {
  echo json_encode(['success' => false, 'message' => 'Payment order not found for this bill.']);
  exit;
}

$booking_id = (int)($paymentRow['booking_id'] ?? 0);

// Verify signature first (fast check).
if (!medikit_razorpay_verify_signature($order_id, $payment_id, $signature)) {
  $upd = $conn->prepare("UPDATE clinic_bill_payments SET status = 'failed' WHERE razorpay_order_id = ? AND bill_id = ? AND patient_id = ?");
  if ($upd) {
    $upd->bind_param('sii', $order_id, $bill_id, $patient_id);
    $upd->execute();
    $upd->close();
  }

  echo json_encode(['success' => false, 'message' => 'Payment signature verification failed.']);
  exit;
}

// Fetch payment details from Razorpay and ensure it is captured.
$fetch = medikit_razorpay_fetch_payment($payment_id);
if (empty($fetch['ok']) || !is_array($fetch['data'])) {
  $msg = (string)($fetch['error'] ?? 'Failed to verify payment with gateway.');
  echo json_encode(['success' => false, 'message' => $msg]);
  exit;
}

$pay = $fetch['data'];
$payOrderId = (string)($pay['order_id'] ?? '');
$payStatus = strtolower((string)($pay['status'] ?? ''));
$captured = (bool)($pay['captured'] ?? false);
$payAmount = (int)($pay['amount'] ?? 0);
$payCurrency = strtoupper((string)($pay['currency'] ?? ''));

if ($payOrderId !== $order_id) {
  echo json_encode(['success' => false, 'message' => 'Payment order mismatch.']);
  exit;
}

if (!($payStatus === 'captured' || $captured)) {
  echo json_encode(['success' => false, 'message' => 'Payment not captured yet.']);
  exit;
}

$expectedPaise = medikit_money_to_paise((string)($paymentRow['amount'] ?? '0'));
$expectedCurrency = strtoupper((string)($paymentRow['currency'] ?? 'INR'));

if ($expectedPaise > 0 && $payAmount > 0 && $payAmount !== $expectedPaise) {
  echo json_encode(['success' => false, 'message' => 'Payment amount mismatch.']);
  exit;
}

if ($expectedCurrency !== '' && $payCurrency !== '' && $payCurrency !== $expectedCurrency) {
  echo json_encode(['success' => false, 'message' => 'Payment currency mismatch.']);
  exit;
}

$razorpayMethod = (string)($pay['method'] ?? '');
$billPaymentMethod = billing_normalize_payment_method('Online');

$conn->begin_transaction();
try {
  $up = $conn->prepare("UPDATE clinic_bill_payments SET razorpay_payment_id = ?, razorpay_signature = ?, razorpay_method = ?, status = 'paid', paid_at = NOW() WHERE razorpay_order_id = ? AND bill_id = ? AND patient_id = ?");
  if ($up) {
    $up->bind_param('ssssii', $payment_id, $signature, $razorpayMethod, $order_id, $bill_id, $patient_id);
    $up->execute();
    $up->close();
  }

  $bup = $conn->prepare("UPDATE clinic_bills SET payment_status = 'paid', payment_method = ? WHERE id = ? AND patient_id = ?");
  if ($bup) {
    $bup->bind_param('sii', $billPaymentMethod, $bill_id, $patient_id);
    $bup->execute();
    $bup->close();
  }

  if ($booking_id > 0) {
    medikit_commission_upsert_for_booking($conn, $booking_id);
  }

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => 'Failed to update bill after payment.']);
  exit;
}

echo json_encode(['success' => true, 'message' => 'Payment verified and bill marked as paid.']);
