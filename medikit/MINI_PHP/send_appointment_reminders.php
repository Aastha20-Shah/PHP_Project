<?php

// Run this script on a schedule (cron / Windows Task Scheduler).
// It sends ONE reminder email per booking ~24 hours before the appointment start time.

include_once __DIR__ . '/config.php';
include_once __DIR__ . '/mailer_helpers.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
  echo "DB connection not available.\n";
  exit(1);
}

medikit_visit_booking_reminder_ensure_schema($conn);

if (!medikit_mail_is_configured()) {
  echo "Mailer not configured or disabled.\n";
  exit(2);
}

$windowMinutes = 2; // run every 1 minute recommended for near-exact timing
$offsetHours = 24;

$now = new DateTimeImmutable('now');
$target = $now->modify('+' . $offsetHours . ' hours');
$start = $target->modify('-' . $windowMinutes . ' minutes');
$end = $target; // don't send early; only send when reminder time has passed

$startStr = $start->format('Y-m-d H:i:s');
$endStr = $end->format('Y-m-d H:i:s');

$sql = "
    SELECT
        vb.id,
        TIMESTAMP(vb.appointment_date, dat.start_time) AS appointment_dt
    FROM visit_booking vb
    INNER JOIN doctor_available_time dat ON dat.id = vb.time_id
    WHERE vb.status IN ('pending','accepted')
      AND vb.patient_id <> 0
      AND vb.appointment_date IS NOT NULL
      AND vb.time_id <> 0
      AND vb.reminder_sent_at IS NULL
      AND TIMESTAMP(vb.appointment_date, dat.start_time) BETWEEN ? AND ?
    ORDER BY appointment_dt ASC
    LIMIT 200
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo "Failed to prepare reminder query.\n";
  exit(3);
}

$stmt->bind_param('ss', $startStr, $endStr);
$stmt->execute();
$res = $stmt->get_result();
$due = [];
while ($res && ($row = $res->fetch_assoc())) {
  $due[] = $row;
}
$stmt->close();

$sent = 0;
$failed = 0;

$markStmt = $conn->prepare("UPDATE visit_booking SET reminder_sent_at = NOW() WHERE id = ? AND reminder_sent_at IS NULL");

foreach ($due as $row) {
  $bookingId = (int)($row['id'] ?? 0);
  if ($bookingId <= 0) {
    continue;
  }

  $ok = medikit_send_patient_appointment_reminder_email($conn, $bookingId);

  if ($ok) {
    $sent++;
    if ($markStmt) {
      $markStmt->bind_param('i', $bookingId);
      $markStmt->execute();
    }
  } else {
    $failed++;
  }
}

if ($markStmt) {
  $markStmt->close();
}

echo "Reminder window: {$startStr} -> {$endStr}\n";
echo "Due: " . count($due) . " | Sent: {$sent} | Failed: {$failed}\n";

exit(0);
