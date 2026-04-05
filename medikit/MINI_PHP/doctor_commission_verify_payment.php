<?php

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/razorpay_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['doctor_id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$doctor_id = (int)$_SESSION['doctor_id'];
if ($doctor_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid doctor.']);
  exit;
}

$order_id = trim((string)($_POST['razorpay_order_id'] ?? ''));
$payment_id = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$signature = trim((string)($_POST['razorpay_signature'] ?? ''));

if ($order_id === '' || $payment_id === '' || $signature === '') {
  echo json_encode(['success' => false, 'message' => 'Missing parameters.']);
  exit;
}

medikit_commission_ensure_schema($conn);
medikit_commission_payments_ensure_schema($conn);

// Load payment record for this doctor/order.
$pstmt = $conn->prepare('SELECT id, amount, currency, status FROM doctor_commission_payments WHERE razorpay_order_id = ? AND doctor_id = ? LIMIT 1');
if (!$pstmt) {
  echo json_encode(['success' => false, 'message' => 'Failed to verify payment.']);
  exit;
}

$pstmt->bind_param('si', $order_id, $doctor_id);
$pstmt->execute();
$paymentRow = $pstmt->get_result()->fetch_assoc();
$pstmt->close();

if (!$paymentRow) {
  echo json_encode(['success' => false, 'message' => 'Payment order not found.']);
  exit;
}

if ((string)($paymentRow['status'] ?? '') === 'paid') {
  echo json_encode(['success' => true, 'message' => 'Already paid.']);
  exit;
}

// Verify signature first.
if (!medikit_razorpay_verify_signature($order_id, $payment_id, $signature)) {
  $upd = $conn->prepare("UPDATE doctor_commission_payments SET status = 'failed' WHERE razorpay_order_id = ? AND doctor_id = ?");
  if ($upd) {
    $upd->bind_param('si', $order_id, $doctor_id);
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

$expectedPaise = medikit_money_to_paise(number_format((float)($paymentRow['amount'] ?? 0), 2, '.', ''));
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
$paymentDbId = (int)($paymentRow['id'] ?? 0);

if ($paymentDbId <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid payment record.']);
  exit;
}

$conn->begin_transaction();
try {
  $up = $conn->prepare("UPDATE doctor_commission_payments
        SET razorpay_payment_id = ?,
            razorpay_signature = ?,
            razorpay_method = ?,
            status = 'paid',
            paid_at = NOW()
        WHERE id = ? AND doctor_id = ?");
  if ($up) {
    $up->bind_param('sssii', $payment_id, $signature, $razorpayMethod, $paymentDbId, $doctor_id);
    $up->execute();
    $up->close();
  }

  $cup = $conn->prepare("UPDATE doctor_commissions dc
        INNER JOIN doctor_commission_payment_items it ON it.commission_id = dc.id
        SET dc.status = 'paid', dc.paid_at = NOW()
        WHERE it.payment_id = ? AND dc.doctor_id = ? AND dc.status = 'due'");
  if ($cup) {
    $cup->bind_param('ii', $paymentDbId, $doctor_id);
    $cup->execute();
    $cup->close();
  }

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => 'Failed to update commission after payment.']);
  exit;
}

echo json_encode(['success' => true, 'message' => 'Commission payment verified.']);
