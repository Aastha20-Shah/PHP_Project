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

medikit_commission_ensure_schema($conn);
medikit_commission_payments_ensure_schema($conn);

try {
  $cfg = medikit_razorpay_config();
} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => 'Payment gateway is not configured.']);
  exit;
}

// Load doctor details for prefill.
$doctor = [
  'firstname' => 'Doctor',
  'lastname' => '',
  'email' => '',
  'phone_number' => '',
];

$dstmt = $conn->prepare('SELECT firstname, lastname, email, phone_number FROM users WHERE id = ? AND role_id = 2 LIMIT 1');
if ($dstmt) {
  $dstmt->bind_param('i', $doctor_id);
  $dstmt->execute();
  $row = $dstmt->get_result()->fetch_assoc();
  $dstmt->close();
  if ($row) {
    $doctor['firstname'] = (string)($row['firstname'] ?? 'Doctor');
    $doctor['lastname'] = (string)($row['lastname'] ?? '');
    $doctor['email'] = (string)($row['email'] ?? '');
    $doctor['phone_number'] = (string)($row['phone_number'] ?? '');
  }
}

// Build a snapshot of due commissions (only for paid bills).
$dueRows = [];
$stmt = $conn->prepare(
  "SELECT dc.id, dc.commission_amount
     FROM doctor_commissions dc
     INNER JOIN clinic_bills cb ON cb.id = dc.bill_id
    WHERE dc.doctor_id = ?
      AND dc.status = 'due'
      AND cb.payment_status = 'paid'
    ORDER BY dc.id ASC"
);

if (!$stmt) {
  echo json_encode(['success' => false, 'message' => 'Failed to load commission due.']);
  exit;
}

$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $dueRows[] = $r;
  }
}
$stmt->close();

$total = 0.0;
foreach ($dueRows as $r) {
  $total += (float)($r['commission_amount'] ?? 0);
}

$total = round($total, 2);
if ($total <= 0 || empty($dueRows)) {
  echo json_encode(['success' => false, 'message' => 'No commission due right now.']);
  exit;
}

$amountStr = number_format($total, 2, '.', '');
$amountPaise = medikit_money_to_paise($amountStr);
if ($amountPaise <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid commission amount.']);
  exit;
}

$receipt = 'commission_doctor_' . $doctor_id . '_' . time();
$notes = [
  'type' => 'doctor_commission',
  'doctor_id' => (string)$doctor_id,
  'count' => (string)count($dueRows),
];

$create = medikit_razorpay_create_order($amountPaise, (string)$cfg['currency'], $receipt, $notes);
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

$conn->begin_transaction();
try {
  $insert = $conn->prepare('INSERT INTO doctor_commission_payments (doctor_id, gateway, amount, currency, razorpay_order_id, status, snapshot_count) VALUES (?,?,?,?,?,?,?)');
  if (!$insert) {
    throw new RuntimeException('Failed to save payment record.');
  }

  $gateway = 'razorpay';
  $amount = (float)$amountStr;
  $currency = (string)($cfg['currency'] ?? 'INR');
  $status = 'created';
  $snapshotCount = (int)count($dueRows);

  $insert->bind_param('isdsssi', $doctor_id, $gateway, $amount, $currency, $order_id, $status, $snapshotCount);
  $insert->execute();
  $payment_id = (int)$insert->insert_id;
  $insert->close();

  if ($payment_id <= 0) {
    throw new RuntimeException('Failed to create payment record.');
  }

  $itemStmt = $conn->prepare('INSERT IGNORE INTO doctor_commission_payment_items (payment_id, commission_id, commission_amount) VALUES (?,?,?)');
  if (!$itemStmt) {
    throw new RuntimeException('Failed to save payment items.');
  }

  foreach ($dueRows as $r) {
    $commissionId = (int)($r['id'] ?? 0);
    $commissionAmt = (float)number_format((float)($r['commission_amount'] ?? 0), 2, '.', '');
    if ($commissionId <= 0) {
      continue;
    }
    $itemStmt->bind_param('iid', $payment_id, $commissionId, $commissionAmt);
    $itemStmt->execute();
  }

  $itemStmt->close();
  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => 'Failed to start commission payment.']);
  exit;
}

$doctor_name = trim($doctor['firstname'] . ' ' . $doctor['lastname']);
if ($doctor_name === '') {
  $doctor_name = 'Doctor';
}

echo json_encode([
  'success' => true,
  'key_id' => (string)$cfg['key_id'],
  'order_id' => $order_id,
  'amount' => (int)($order['amount'] ?? $amountPaise),
  'currency' => (string)($order['currency'] ?? $cfg['currency']),
  'name' => 'Medkit',
  'description' => 'Commission payment (' . count($dueRows) . ' items)',
  'prefill' => [
    'name' => $doctor_name,
    'email' => (string)$doctor['email'],
    'contact' => (string)$doctor['phone_number'],
  ],
]);
