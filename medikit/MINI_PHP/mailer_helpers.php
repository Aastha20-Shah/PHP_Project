<?php

include_once __DIR__ . '/mailer_config.php';

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function medikit_mail_is_configured(): bool
{
  if (!defined('MEDIKIT_MAIL_ENABLED') || MEDIKIT_MAIL_ENABLED !== true) {
    return false;
  }

  $host = defined('MEDIKIT_SMTP_HOST') ? trim((string)MEDIKIT_SMTP_HOST) : '';
  $user = defined('MEDIKIT_SMTP_USERNAME') ? trim((string)MEDIKIT_SMTP_USERNAME) : '';
  $pass = defined('MEDIKIT_SMTP_PASSWORD') ? trim((string)MEDIKIT_SMTP_PASSWORD) : '';
  $from = defined('MEDIKIT_MAIL_FROM') ? trim((string)MEDIKIT_MAIL_FROM) : '';
  $port = defined('MEDIKIT_SMTP_PORT') ? (int)MEDIKIT_SMTP_PORT : 0;

  return ($host !== '' && $user !== '' && $pass !== '' && $from !== '' && $port > 0);
}

function medikit_send_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = '', array $attachments = []): bool
{
  $toEmail = trim($toEmail);
  if ($toEmail === '') {
    return false;
  }

  if (!medikit_mail_is_configured()) {
    return false;
  }

  try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';

    if (defined('MEDIKIT_MAIL_DEBUG') && MEDIKIT_MAIL_DEBUG) {
      $mail->SMTPDebug = 2;
      $mail->Debugoutput = function ($str, $level) {
        error_log('[PHPMailer][' . $level . '] ' . $str);
      };
    } else {
      $mail->SMTPDebug = 0;
    }

    $mail->isSMTP();
    $mail->Host = (string)MEDIKIT_SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = (string)MEDIKIT_SMTP_USERNAME;
    $mail->Password = (string)MEDIKIT_SMTP_PASSWORD;
    $mail->SMTPSecure = (string)MEDIKIT_SMTP_SECURE;
    $mail->Port = (int)MEDIKIT_SMTP_PORT;

    $fromEmail = (string)MEDIKIT_MAIL_FROM;
    $fromName = defined('MEDIKIT_MAIL_FROM_NAME') ? (string)MEDIKIT_MAIL_FROM_NAME : 'Medkit';
    $mail->setFrom($fromEmail, $fromName);

    $mail->addAddress($toEmail, trim($toName) !== '' ? $toName : $toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;

    if ($altBody !== '') {
      $mail->AltBody = $altBody;
    } else {
      $mail->AltBody = trim(strip_tags($htmlBody));
    }

    if (!empty($attachments)) {
      foreach ($attachments as $att) {
        if (!is_array($att)) {
          continue;
        }

        $name = trim((string)($att['name'] ?? $att['filename'] ?? 'attachment'));
        $type = trim((string)($att['type'] ?? 'application/octet-stream'));

        if (isset($att['data']) && is_string($att['data']) && $att['data'] !== '' && $name !== '') {
          $mail->addStringAttachment($att['data'], $name, 'base64', $type);
          continue;
        }

        $path = trim((string)($att['path'] ?? ''));
        if ($path !== '' && file_exists($path)) {
          $attachName = $name !== '' ? $name : basename($path);
          $mail->addAttachment($path, $attachName);
        }
      }
    }

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('PHPMailer error: ' . $e->getMessage());
    return false;
  } catch (Throwable $e) {
    error_log('Mailer unexpected error: ' . $e->getMessage());
    return false;
  }
}

function medikit_send_patient_booking_confirmation_email(mysqli $conn, int $patientId, int $doctorId, string $appointmentDate, int $timeId, int $specialityId = 0): bool
{
  if (!medikit_mail_is_configured()) {
    return false;
  }

  $patient = [];
  $p_stmt = $conn->prepare("SELECT firstname, lastname, email FROM patient WHERE id = ? LIMIT 1");
  if ($p_stmt) {
    $p_stmt->bind_param('i', $patientId);
    $p_stmt->execute();
    $patient = $p_stmt->get_result()->fetch_assoc() ?: [];
    $p_stmt->close();
  }

  $patientEmail = trim((string)($patient['email'] ?? ''));
  $patientName = trim((string)($patient['firstname'] ?? '') . ' ' . (string)($patient['lastname'] ?? ''));
  if ($patientName === '') {
    $patientName = 'Patient';
  }

  if ($patientEmail === '') {
    return false;
  }

  $doctor = [];
  $d_stmt = $conn->prepare("SELECT firstname, lastname, clinic_name, address FROM users WHERE id = ? LIMIT 1");
  if ($d_stmt) {
    $d_stmt->bind_param('i', $doctorId);
    $d_stmt->execute();
    $doctor = $d_stmt->get_result()->fetch_assoc() ?: [];
    $d_stmt->close();
  }

  $doctorName = trim((string)($doctor['firstname'] ?? '') . ' ' . (string)($doctor['lastname'] ?? ''));
  $doctorName = $doctorName !== '' ? ('Dr. ' . $doctorName) : 'Doctor';

  $clinicName = trim((string)($doctor['clinic_name'] ?? ''));
  $clinicAddress = trim((string)($doctor['address'] ?? ''));

  $timeRow = [];
  $t_stmt = $conn->prepare("SELECT start_time, end_time FROM doctor_available_time WHERE id = ? LIMIT 1");
  if ($t_stmt) {
    $t_stmt->bind_param('i', $timeId);
    $t_stmt->execute();
    $timeRow = $t_stmt->get_result()->fetch_assoc() ?: [];
    $t_stmt->close();
  }

  $startTime = (string)($timeRow['start_time'] ?? '');
  $endTime = (string)($timeRow['end_time'] ?? '');
  $timeLabel = 'Time to be confirmed';
  if ($startTime !== '') {
    $timeLabel = date('g:i A', strtotime($startTime));
    if ($endTime !== '') {
      $timeLabel .= ' - ' . date('g:i A', strtotime($endTime));
    }
  }

  $dateLabel = $appointmentDate;
  $ts = strtotime($appointmentDate);
  if ($ts !== false) {
    $dateLabel = date('F j, Y', $ts);
  }

  $specialityLabel = '';
  if ($specialityId > 0) {
    $s_stmt = $conn->prepare("SELECT doctor_speciality FROM speciality WHERE id = ? LIMIT 1");
    if ($s_stmt) {
      $s_stmt->bind_param('i', $specialityId);
      $s_stmt->execute();
      $specRow = $s_stmt->get_result()->fetch_assoc() ?: [];
      $specialityLabel = trim((string)($specRow['doctor_speciality'] ?? ''));
      $s_stmt->close();
    }
  }

  $subject = 'Appointment booked - ' . $doctorName . ' - ' . $dateLabel;

  $pNameEsc = htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8');
  $dNameEsc = htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8');
  $dateEsc = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
  $timeEsc = htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8');
  $clinicEsc = htmlspecialchars($clinicName !== '' ? $clinicName : '-', ENT_QUOTES, 'UTF-8');
  $addrEsc = htmlspecialchars($clinicAddress !== '' ? $clinicAddress : '-', ENT_QUOTES, 'UTF-8');
  $specEsc = htmlspecialchars($specialityLabel !== '' ? $specialityLabel : '-', ENT_QUOTES, 'UTF-8');

  $html = '';
  $html .= '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#1f2a44;">';
  $html .= '<p style="margin:0 0 12px 0;">Hi <strong>' . $pNameEsc . '</strong>,</p>';
  $html .= '<p style="margin:0 0 14px 0;">Your appointment slot has been booked successfully.</p>';

  $html .= '<div style="background:#f6f8fc;border:1px solid #e6edf7;border-radius:12px;padding:14px 16px;">';
  $html .= '<div><strong>Doctor:</strong> ' . $dNameEsc . '</div>';
  $html .= '<div><strong>Date:</strong> ' . $dateEsc . '</div>';
  $html .= '<div><strong>Time:</strong> ' . $timeEsc . '</div>';
  $html .= '<div><strong>Speciality:</strong> ' . $specEsc . '</div>';
  $html .= '<div><strong>Clinic:</strong> ' . $clinicEsc . '</div>';
  $html .= '<div><strong>Address:</strong> ' . $addrEsc . '</div>';
  $html .= '</div>';
  $html .= '<p style="margin:14px 0 0 0;color:#5c6b82;font-size:13px;">Thanks,<br>Medkit</p>';
  $html .= '</div>';

  $alt = "Hi {$patientName},\n\n" .
    "Your appointment slot has been booked successfully.\n\n" .
    "Doctor: {$doctorName}\n" .
    "Date: {$dateLabel}\n" .
    "Time: {$timeLabel}\n" .
    ($specialityLabel !== '' ? "Speciality: {$specialityLabel}\n" : '') .
    ($clinicName !== '' ? "Clinic: {$clinicName}\n" : '') .
    ($clinicAddress !== '' ? "Address: {$clinicAddress}\n" : '') .
    "\nThanks,\nMedikit";

  return medikit_send_mail($patientEmail, $patientName, $subject, $html, $alt);
}

function medikit_visit_booking_reminder_ensure_schema(mysqli $conn): void
{
  $res = $conn->query("SHOW COLUMNS FROM `visit_booking` LIKE 'reminder_sent_at'");
  if ($res && $res->num_rows === 0) {
    $conn->query("ALTER TABLE `visit_booking` ADD COLUMN `reminder_sent_at` DATETIME NULL DEFAULT NULL");
  }
}

function medikit_send_patient_appointment_reminder_email(mysqli $conn, int $bookingId): bool
{
  if ($bookingId <= 0) {
    return false;
  }

  if (!medikit_mail_is_configured()) {
    return false;
  }

  $q = "
        SELECT
            vb.id AS booking_id,
            vb.appointment_date,
            vb.status,
            vb.patient_id,
            vb.doctor_id,
            vb.speciality_id,
            vb.time_id,
            p.firstname AS patient_firstname,
            p.lastname  AS patient_lastname,
            p.email     AS patient_email,
            u.firstname AS doctor_firstname,
            u.lastname  AS doctor_lastname,
            u.clinic_name,
            u.address,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time
        FROM visit_booking vb
        LEFT JOIN patient p ON p.id = vb.patient_id
        LEFT JOIN users u ON u.id = vb.doctor_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE vb.id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('i', $bookingId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();

  if (empty($row)) {
    return false;
  }

  $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
  if (!in_array($statusRaw, ['pending', 'accepted'], true)) {
    return false;
  }

  $patientEmail = trim((string)($row['patient_email'] ?? ''));
  if ($patientEmail === '') {
    return false;
  }

  $patientName = trim((string)($row['patient_firstname'] ?? '') . ' ' . (string)($row['patient_lastname'] ?? ''));
  if ($patientName === '') {
    $patientName = 'Patient';
  }

  $doctorName = trim((string)($row['doctor_firstname'] ?? '') . ' ' . (string)($row['doctor_lastname'] ?? ''));
  $doctorName = $doctorName !== '' ? ('Dr. ' . $doctorName) : 'Doctor';

  $clinicName = trim((string)($row['clinic_name'] ?? ''));
  $clinicAddress = trim((string)($row['address'] ?? ''));
  $specialityLabel = trim((string)($row['doctor_speciality'] ?? ''));

  $startTime = (string)($row['start_time'] ?? '');
  $endTime = (string)($row['end_time'] ?? '');
  $timeLabel = 'Time to be confirmed';
  if ($startTime !== '') {
    $timeLabel = date('g:i A', strtotime($startTime));
    if ($endTime !== '') {
      $timeLabel .= ' - ' . date('g:i A', strtotime($endTime));
    }
  }

  $appointmentDate = (string)($row['appointment_date'] ?? '');
  $dateLabel = $appointmentDate;
  $ts = strtotime($appointmentDate);
  if ($ts !== false) {
    $dateLabel = date('F j, Y', $ts);
  }

  $subject = 'Reminder: Appointment with ' . $doctorName . ' - ' . $dateLabel;

  $pNameEsc = htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8');
  $dNameEsc = htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8');
  $dateEsc = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
  $timeEsc = htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8');
  $clinicEsc = htmlspecialchars($clinicName !== '' ? $clinicName : '-', ENT_QUOTES, 'UTF-8');
  $addrEsc = htmlspecialchars($clinicAddress !== '' ? $clinicAddress : '-', ENT_QUOTES, 'UTF-8');
  $specEsc = htmlspecialchars($specialityLabel !== '' ? $specialityLabel : '-', ENT_QUOTES, 'UTF-8');

  $html = '';
  $html .= '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#1f2a44;">';
  $html .= '<p style="margin:0 0 12px 0;">Hi <strong>' . $pNameEsc . '</strong>,</p>';
  $html .= '<p style="margin:0 0 14px 0;">This is a friendly reminder that you have an appointment scheduled in the next 24 hours.</p>';
  $html .= '<div style="background:#f6f8fc;border:1px solid #e6edf7;border-radius:12px;padding:14px 16px;">';
  $html .= '<div><strong>Doctor:</strong> ' . $dNameEsc . '</div>';
  $html .= '<div><strong>Date:</strong> ' . $dateEsc . '</div>';
  $html .= '<div><strong>Time:</strong> ' . $timeEsc . '</div>';
  $html .= '<div><strong>Speciality:</strong> ' . $specEsc . '</div>';
  $html .= '<div><strong>Clinic:</strong> ' . $clinicEsc . '</div>';
  $html .= '<div><strong>Address:</strong> ' . $addrEsc . '</div>';
  $html .= '</div>';
  $html .= '<p style="margin:14px 0 0 0;color:#5c6b82;font-size:13px;">Please arrive 10 minutes early.</p>';
  $html .= '<p style="margin:14px 0 0 0;color:#5c6b82;font-size:13px;">Thanks,<br>Medkit</p>';
  $html .= '</div>';

  $alt = "Hi {$patientName},\n\n" .
    "Reminder: you have an appointment scheduled in the next 24 hours.\n\n" .
    "Doctor: {$doctorName}\n" .
    "Date: {$dateLabel}\n" .
    "Time: {$timeLabel}\n" .
    ($specialityLabel !== '' ? "Speciality: {$specialityLabel}\n" : '') .
    ($clinicName !== '' ? "Clinic: {$clinicName}\n" : '') .
    ($clinicAddress !== '' ? "Address: {$clinicAddress}\n" : '') .
    "\nThanks,\nMedikit";

  return medikit_send_mail($patientEmail, $patientName, $subject, $html, $alt);
}

function medikit_visit_booking_documents_ensure_schema(mysqli $conn): void
{
  $res = $conn->query("SHOW COLUMNS FROM `visit_booking` LIKE 'documents_emailed_at'");
  if ($res && $res->num_rows === 0) {
    $conn->query("ALTER TABLE `visit_booking` ADD COLUMN `documents_emailed_at` DATETIME NULL DEFAULT NULL");
  }
}

/**
 * Sends bill + prescription PDFs to patient when:
 * - booking is completed (visited)
 * - bill exists
 * - prescription exists
 * - documents haven't already been emailed for this booking
 */
function medikit_send_patient_completed_documents_email_if_ready(mysqli $conn, int $bookingId): array
{
  $out = [
    'ok' => false,
    'status' => 'unknown',
    'message' => '',
  ];

  if ($bookingId <= 0) {
    $out['status'] = 'invalid';
    return $out;
  }

  if (!medikit_mail_is_configured()) {
    $out['status'] = 'mail_disabled';
    $out['message'] = 'Mailer not configured or disabled.';
    return $out;
  }

  medikit_visit_booking_documents_ensure_schema($conn);

  $q = "
        SELECT
            vb.id AS booking_id,
            vb.status,
            vb.documents_emailed_at,
            vb.appointment_date,
            p.firstname AS patient_firstname,
            p.lastname  AS patient_lastname,
            p.email     AS patient_email,
            u.firstname AS doctor_firstname,
            u.lastname  AS doctor_lastname,
            u.clinic_name,
            u.address AS clinic_address,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time
        FROM visit_booking vb
        LEFT JOIN patient p ON p.id = vb.patient_id
        LEFT JOIN users u ON u.id = vb.doctor_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE vb.id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    $out['status'] = 'db_error';
    $out['message'] = 'Failed to prepare booking query.';
    return $out;
  }

  $stmt->bind_param('i', $bookingId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: [];
  $stmt->close();

  if (empty($row)) {
    $out['status'] = 'not_found';
    $out['message'] = 'Booking not found.';
    return $out;
  }

  if (trim((string)($row['documents_emailed_at'] ?? '')) !== '') {
    $out['status'] = 'already_sent';
    return $out;
  }

  $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
  if ($statusRaw !== 'visited') {
    $out['status'] = 'not_completed';
    $out['message'] = 'Booking is not completed yet.';
    return $out;
  }

  $patientEmail = trim((string)($row['patient_email'] ?? ''));
  if ($patientEmail === '') {
    $out['status'] = 'missing_patient_email';
    $out['message'] = 'Patient email not found.';
    return $out;
  }

  include_once __DIR__ . '/pdf_helpers.php';
  if (!function_exists('medikit_pdf_make_bill_attachment') || !function_exists('medikit_pdf_make_prescription_attachment')) {
    $out['status'] = 'pdf_unavailable';
    $out['message'] = 'PDF generator not available.';
    return $out;
  }

  if (function_exists('billing_ensure_schema')) {
    try {
      billing_ensure_schema($conn);
    } catch (Throwable $e) {
      // ignore
    }
  }
  if (function_exists('prescriptions_ensure_schema')) {
    try {
      prescriptions_ensure_schema($conn);
    } catch (Throwable $e) {
      // ignore
    }
  }

  $billExists = false;
  $bStmt = $conn->prepare('SELECT id FROM clinic_bills WHERE booking_id = ? LIMIT 1');
  if ($bStmt) {
    $bStmt->bind_param('i', $bookingId);
    $bStmt->execute();
    $billExists = (bool)($bStmt->get_result()->fetch_assoc());
    $bStmt->close();
  }

  if (!$billExists) {
    $out['status'] = 'pending_bill';
    $out['message'] = 'Bill not created yet.';
    return $out;
  }

  $rxExists = false;
  $rStmt = $conn->prepare('SELECT id FROM clinic_prescriptions WHERE booking_id = ? LIMIT 1');
  if ($rStmt) {
    $rStmt->bind_param('i', $bookingId);
    $rStmt->execute();
    $rxExists = (bool)($rStmt->get_result()->fetch_assoc());
    $rStmt->close();
  }

  if (!$rxExists) {
    $out['status'] = 'pending_prescription';
    $out['message'] = 'Prescription not created yet.';
    return $out;
  }

  $billAtt = medikit_pdf_make_bill_attachment($conn, $bookingId);
  $rxAtt = medikit_pdf_make_prescription_attachment($conn, $bookingId);

  if (!is_array($billAtt) || empty($billAtt['data']) || empty($billAtt['filename'])) {
    $out['status'] = 'pdf_failed';
    $out['message'] = 'Failed to generate bill PDF.';
    return $out;
  }
  if (!is_array($rxAtt) || empty($rxAtt['data']) || empty($rxAtt['filename'])) {
    $out['status'] = 'pdf_failed';
    $out['message'] = 'Failed to generate prescription PDF.';
    return $out;
  }

  $patientName = trim((string)($row['patient_firstname'] ?? '') . ' ' . (string)($row['patient_lastname'] ?? ''));
  if ($patientName === '') {
    $patientName = 'Patient';
  }

  $doctorName = trim((string)($row['doctor_firstname'] ?? '') . ' ' . (string)($row['doctor_lastname'] ?? ''));
  $doctorName = $doctorName !== '' ? ('Dr. ' . $doctorName) : 'Doctor';

  $clinicName = trim((string)($row['clinic_name'] ?? ''));
  $clinicAddress = trim((string)($row['clinic_address'] ?? ''));
  $specialityLabel = trim((string)($row['doctor_speciality'] ?? ''));

  $senderLabel = $clinicName !== '' ? $clinicName : $doctorName;
  if (trim($senderLabel) === '') {
    $senderLabel = 'Clinic';
  }

  $startTime = (string)($row['start_time'] ?? '');
  $endTime = (string)($row['end_time'] ?? '');
  $timeLabel = 'Time to be confirmed';
  if ($startTime !== '') {
    $timeLabel = date('g:i A', strtotime($startTime));
    if ($endTime !== '') {
      $timeLabel .= ' - ' . date('g:i A', strtotime($endTime));
    }
  }

  $appointmentDate = (string)($row['appointment_date'] ?? '');
  $dateLabel = $appointmentDate;
  $ts = strtotime($appointmentDate);
  if ($ts !== false) {
    $dateLabel = date('F j, Y', $ts);
  }

  $subject = 'Thank you - Bill & Prescription - ' . $doctorName . ' - ' . $dateLabel;

  $pNameEsc = htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8');
  $dNameEsc = htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8');
  $dateEsc = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
  $timeEsc = htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8');
  $clinicEsc = htmlspecialchars($clinicName !== '' ? $clinicName : '-', ENT_QUOTES, 'UTF-8');
  $addrEsc = htmlspecialchars($clinicAddress !== '' ? $clinicAddress : '-', ENT_QUOTES, 'UTF-8');
  $specEsc = htmlspecialchars($specialityLabel !== '' ? $specialityLabel : '-', ENT_QUOTES, 'UTF-8');
  $senderEsc = htmlspecialchars($senderLabel, ENT_QUOTES, 'UTF-8');

  $html = '';
  $html .= '<div style="font-family:Arial,Helvetica,sans-serif;line-height:1.5;color:#1f2a44;">';
  $html .= '<p style="margin:0 0 12px 0;">Hi <strong>' . $pNameEsc . '</strong>,</p>';
  $html .= '<p style="margin:0 0 14px 0;">Thank you for visiting. Your appointment has been completed.</p>';
  $html .= '<p style="margin:0 0 14px 0;">We have attached your <strong>Bill</strong> and <strong>Prescription</strong> as PDF files.</p>';
  $html .= '<div style="background:#f6f8fc;border:1px solid #e6edf7;border-radius:12px;padding:14px 16px;">';
  $html .= '<div><strong>Doctor:</strong> ' . $dNameEsc . '</div>';
  $html .= '<div><strong>Date:</strong> ' . $dateEsc . '</div>';
  $html .= '<div><strong>Time:</strong> ' . $timeEsc . '</div>';
  $html .= '<div><strong>Speciality:</strong> ' . $specEsc . '</div>';
  $html .= '<div><strong>Clinic:</strong> ' . $clinicEsc . '</div>';
  $html .= '<div><strong>Address:</strong> ' . $addrEsc . '</div>';
  $html .= '</div>';
  $html .= '<p style="margin:14px 0 0 0;color:#5c6b82;font-size:13px;">Thanks,<br>' . $senderEsc . '</p>';
  $html .= '</div>';

  $alt = "Hi {$patientName},\n\n" .
    "Thank you for visiting. Your appointment has been completed.\n" .
    "Attached: Bill (PDF) and Prescription (PDF).\n\n" .
    "Doctor: {$doctorName}\n" .
    "Date: {$dateLabel}\n" .
    "Time: {$timeLabel}\n" .
    ($specialityLabel !== '' ? "Speciality: {$specialityLabel}\n" : '') .
    ($clinicName !== '' ? "Clinic: {$clinicName}\n" : '') .
    ($clinicAddress !== '' ? "Address: {$clinicAddress}\n" : '') .
    "\nThanks,\n{$senderLabel}";

  $attachments = [
    ['data' => (string)$billAtt['data'], 'name' => (string)$billAtt['filename'], 'type' => 'application/pdf'],
    ['data' => (string)$rxAtt['data'], 'name' => (string)$rxAtt['filename'], 'type' => 'application/pdf'],
  ];

  $mailOk = medikit_send_mail($patientEmail, $patientName, $subject, $html, $alt, $attachments);

  if (!$mailOk) {
    $out['status'] = 'send_failed';
    $out['message'] = 'Failed to send email.';
    return $out;
  }

  $up = $conn->prepare('UPDATE visit_booking SET documents_emailed_at = NOW() WHERE id = ? AND documents_emailed_at IS NULL');
  if ($up) {
    $up->bind_param('i', $bookingId);
    $up->execute();
    $up->close();
  }

  $out['ok'] = true;
  $out['status'] = 'sent';
  return $out;
}
