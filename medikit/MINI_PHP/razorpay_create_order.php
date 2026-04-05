<?php

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/billing_helpers.php';
require_once __DIR__ . '/razorpay_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['patient_id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];
$bill_id = (int)($_POST['bill_id'] ?? 0);

if ($bill_id <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid bill.']);
  exit;
}

try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Billing is not available right now.']);
  exit;
}

$q = "
    SELECT
      cb.id AS bill_id,
      cb.booking_id,
      cb.doctor_id,
      cb.patient_id,
      cb.amount,
      cb.payment_status,
      u.clinic_name,
      u.firstname AS doc_firstname,
      u.lastname AS doc_lastname,
      p.firstname AS patient_firstname,
      p.lastname AS patient_lastname,
      p.phone_number,
      p.email
    FROM clinic_bills cb
    JOIN users u ON u.id = cb.doctor_id
    JOIN patient p ON p.id = cb.patient_id
    WHERE cb.id = ? AND cb.patient_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($q);
if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Failed to load bill.']);
  exit;
}

$stmt->bind_param('ii', $bill_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
  echo json_encode(['success' => false, 'message' => 'Bill not found.']);
  exit;
}

if (($row['payment_status'] ?? '') === 'paid') {
  echo json_encode(['success' => false, 'message' => 'Bill is already paid.', 'already_paid' => true]);
  exit;
}

$amountStr = (string)($row['amount'] ?? '0');
$amountPaise = medikit_money_to_paise($amountStr);
if ($amountPaise <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid bill amount.']);
  exit;
}

try {
  $cfg = medikit_razorpay_config();
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Payment gateway is not configured.']);
  exit;
}

$receipt = 'bill_' . $bill_id . '_' . time();
$notes = [
  'bill_id' => (string)$bill_id,
  'booking_id' => (string)($row['booking_id'] ?? ''),
  'patient_id' => (string)$patient_id,
  'doctor_id' => (string)($row['doctor_id'] ?? ''),
];

$create = medikit_razorpay_create_order($amountPaise, $cfg['currency'], $receipt, $notes);
if (empty($create['ok'])) {
  $msg = (string)($create['error'] ?? 'Failed to create order.');
  echo json_encode(['success' => false, 'message' => $msg]);
  exit;
}

$order = $create['data'];
$order_id = is_array($order) ? (string)($order['id'] ?? '') : '';
if ($order_id === '') {
  echo json_encode(['success' => false, 'message' => 'Invalid order response from gateway.']);
  exit;
}

$insert = $conn->prepare('INSERT INTO clinic_bill_payments (bill_id, booking_id, doctor_id, patient_id, gateway, amount, currency, razorpay_order_id, status) VALUES (?,?,?,?,?,?,?,?,?)');
if ($insert) {
  $booking_id = (int)($row['booking_id'] ?? 0);
  $doctor_id = (int)($row['doctor_id'] ?? 0);
  $amount = (float)$amountStr;
  $gateway = 'razorpay';
  $currency = (string)($cfg['currency'] ?? 'INR');
  $status = 'created';
  $insert->bind_param('iiiisdsss', $bill_id, $booking_id, $doctor_id, $patient_id, $gateway, $amount, $currency, $order_id, $status);
  $insert->execute();
  $insert->close();
}

$clinic_name = trim((string)($row['clinic_name'] ?? ''));
if ($clinic_name === '') {
  $clinic_name = 'Clinic';
}

$patient_name = trim((string)($row['patient_firstname'] ?? '') . ' ' . (string)($row['patient_lastname'] ?? ''));
$patient_email = (string)($row['email'] ?? '');
$patient_contact = (string)($row['phone_number'] ?? '');

$invoice_no = billing_invoice_no($bill_id);

echo json_encode([
  'success' => true,
  'key_id' => $cfg['key_id'],
  'order_id' => $order_id,
  'amount' => (int)($order['amount'] ?? $amountPaise),
  'currency' => (string)($order['currency'] ?? $cfg['currency']),
  'name' => $clinic_name,
  'description' => 'Invoice #' . $invoice_no,
  'prefill' => [
    'name' => $patient_name,
    'email' => $patient_email,
    'contact' => $patient_contact,
  ],
  'notes' => [
    'bill_id' => $bill_id,
    'invoice_no' => $invoice_no,
  ],
]);
