<?php

declare(strict_types=1);

include_once __DIR__ . '/billing_helpers.php';
include_once __DIR__ . '/prescription_helpers.php';

function medikit_pdf_autoload(): bool
{
  static $loaded = false;
  static $attempted = false;

  if ($loaded) {
    return true;
  }

  if ($attempted) {
    return false;
  }

  $attempted = true;
  $autoload = __DIR__ . '/vendor/autoload.php';
  if (!file_exists($autoload)) {
    return false;
  }

  require_once $autoload;

  $loaded = class_exists('Dompdf\\Dompdf');
  return $loaded;
}

function medikit_pdf_render_html(string $html, string $paper = 'A4', string $orientation = 'portrait'): ?string
{
  if (!medikit_pdf_autoload()) {
    return null;
  }

  try {
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper($paper, $orientation);
    $dompdf->render();

    return $dompdf->output();
  } catch (Throwable $e) {
    error_log('PDF render error: ' . $e->getMessage());
    return null;
  }
}

function medikit_pdf_doc(string $title, string $bodyHtml, string $brand = 'Medkit'): string
{
  $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  $brand = trim($brand);
  if ($brand === '') {
    $brand = 'Clinic';
  }
  $brandEsc = htmlspecialchars($brand, ENT_QUOTES, 'UTF-8');

  // Theme tokens (match MINI_PHP/custom_style.css)
  $primary = '#1A76D1';
  $secondary = '#F4F7FE';
  $heading = '#212529';
  $text = '#6C757D';
  $border = '#E5E7EB';

  $css = '';
  $css .= '@page{margin:24px 28px;}';
  $css .= 'body{margin:0;font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;line-height:1.4;color:' . $heading . ';}';
  $css .= 'div,table,td,th{box-sizing:border-box;}';
  $css .= '.muted{color:' . $text . ';}';
  $css .= '.doc-header{background:' . $primary . ';color:#fff;border-radius:12px;padding:16px 18px;}';
  $css .= '.doc-header table{width:100%;border-collapse:collapse;}';
  $css .= '.brand{font-size:20px;font-weight:800;letter-spacing:0.2px;}';
  $css .= '.doc-title{text-align:right;font-size:13px;font-weight:700;}';
  $css .= '.doc-content{margin-top:14px;}';
  $css .= '.h1{font-size:18px;font-weight:800;margin:0 0 6px 0;color:' . $heading . ';}';
  $css .= '.h2{font-size:13px;font-weight:800;margin:0 0 8px 0;color:' . $primary . ';}';
  $css .= '.h3{font-size:11px;font-weight:800;margin:0 0 4px 0;color:' . $heading . ';}';
  $css .= '.section{margin-top:14px;}';
  $css .= '.box{border:1px solid ' . $border . ';border-radius:12px;padding:14px;background:' . $secondary . ';}';
  $css .= '.box.white{background:#fff;}';
  $css .= '.row{width:100%;}';
  $css .= '.col{display:inline-block;vertical-align:top;width:49%;}';
  $css .= '.tbl{width:100%;border-collapse:collapse;table-layout:fixed;}';
  $css .= '.tbl th,.tbl td{border:1px solid ' . $border . ';padding:9px 10px;vertical-align:top;word-wrap:break-word;}';
  $css .= '.tbl th{background:' . $secondary . ';text-align:left;font-weight:800;color:' . $heading . ';}';
  $css .= 'table{page-break-inside:auto;}';
  $css .= 'tr{page-break-inside:avoid;page-break-after:auto;}';

  return '<!doctype html>' .
    '<html><head><meta charset="UTF-8"><title>' . $titleEsc . '</title><style>' . $css . '</style></head>' .
    '<body>' .
    '<div class="doc-header"><table><tr><td class="brand">' . $brandEsc . '</td><td class="doc-title">' . $titleEsc . '</td></tr></table></div>' .
    '<div class="doc-content">' . $bodyHtml . '</div>' .
    '</body></html>';
}

function medikit_pdf_fetch_bill_row(mysqli $conn, int $bookingId): ?array
{
  $q = "
        SELECT
            cb.id AS bill_id,
            cb.booking_id,
            cb.service_type,
            cb.amount,
            cb.payment_method,
            cb.payment_status,
            cb.created_at,
            vb.appointment_date,
            vb.Note,
            u.firstname AS doc_firstname,
            u.lastname AS doc_lastname,
            u.clinic_name,
            u.address AS clinic_address,
            u.phone_number AS clinic_phone,
            u.email AS clinic_email,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            p.email AS patient_email,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time
        FROM clinic_bills cb
        JOIN visit_booking vb ON vb.id = cb.booking_id
        JOIN users u ON u.id = cb.doctor_id
        LEFT JOIN patient p ON p.id = cb.patient_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE cb.booking_id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param('i', $bookingId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: null;
  $stmt->close();

  return $row;
}

function medikit_pdf_fetch_prescription_row(mysqli $conn, int $bookingId): ?array
{
  $q = "
        SELECT
            cp.*,
            vb.appointment_date,
            vb.Note,
            u.firstname AS doc_firstname,
            u.lastname AS doc_lastname,
            u.clinic_name,
            u.address AS clinic_address,
            u.phone_number AS clinic_phone,
            u.email AS clinic_email,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            p.email AS patient_email,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time
        FROM clinic_prescriptions cp
        LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
        LEFT JOIN users u ON u.id = cp.doctor_id
        LEFT JOIN patient p ON p.id = cp.patient_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE cp.booking_id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param('i', $bookingId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc() ?: null;
  $stmt->close();

  return $row;
}

function medikit_pdf_make_bill_attachment(mysqli $conn, int $bookingId): ?array
{
  $bill = medikit_pdf_fetch_bill_row($conn, $bookingId);
  if (!$bill) {
    return null;
  }

  $billId = (int)($bill['bill_id'] ?? 0);
  if ($billId <= 0) {
    return null;
  }

  $invoiceNo = billing_invoice_no($billId);

  $doctorName = trim((string)($bill['doc_firstname'] ?? '') . ' ' . (string)($bill['doc_lastname'] ?? ''));
  $doctorName = $doctorName !== '' ? ('Dr. ' . $doctorName) : 'Doctor';

  $patientName = trim((string)($bill['patient_firstname'] ?? '') . ' ' . (string)($bill['patient_lastname'] ?? ''));
  $patientName = $patientName !== '' ? $patientName : 'Patient';

  $apptDate = !empty($bill['appointment_date']) ? date('F j, Y', strtotime((string)$bill['appointment_date'])) : '-';
  $startTime = (string)($bill['start_time'] ?? '');
  $endTime = (string)($bill['end_time'] ?? '');
  $apptTime = '-';
  if ($startTime !== '') {
    $apptTime = date('g:i A', strtotime($startTime));
    if ($endTime !== '') {
      $apptTime .= ' - ' . date('g:i A', strtotime($endTime));
    }
  }

  $clinicName = trim((string)($bill['clinic_name'] ?? ''));
  $clinicAddr = trim((string)($bill['clinic_address'] ?? ''));
  $clinicPhone = trim((string)($bill['clinic_phone'] ?? ''));
  $clinicEmail = trim((string)($bill['clinic_email'] ?? ''));

  $brand = $clinicName !== '' ? $clinicName : $doctorName;
  if (trim($brand) === '') {
    $brand = 'Clinic';
  }

  $serviceType = (string)($bill['service_type'] ?? 'Consultation');
  $amount = number_format((float)($bill['amount'] ?? 0), 2);
  $paymentMethod = (string)($bill['payment_method'] ?? '');
  $paymentStatus = (string)($bill['payment_status'] ?? 'pending');
  $notes = trim((string)($bill['Note'] ?? ''));

  $body = '';
  $body .= '<div class="box">';
  $body .= '<div class="row">';
  $body .= '<div class="col"><div class="h3">Doctor</div>';
  $body .= '<div>' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '<div class="muted">' . htmlspecialchars((string)($bill['doctor_speciality'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '<div class="col"><div class="h3">Patient</div>';
  $body .= '<div>' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '</div>';

  $body .= '<div class="row" style="margin-top:10px;">';
  $body .= '<div class="col"><div class="h3">Appointment</div>';
  $body .= '<div>' . htmlspecialchars($apptDate, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '<div class="muted">' . htmlspecialchars($apptTime, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '<div class="col"><div class="h3">Clinic</div>';
  $body .= '<div>' . htmlspecialchars($clinicName !== '' ? $clinicName : '-', ENT_QUOTES, 'UTF-8') . '</div>';
  $clinicMeta = $clinicAddr !== '' ? $clinicAddr : '-';
  if ($clinicPhone !== '') {
    $clinicMeta .= "\nPhone: " . $clinicPhone;
  }
  if ($clinicEmail !== '') {
    $clinicMeta .= "\nEmail: " . $clinicEmail;
  }
  $body .= '<div class="muted">' . nl2br(htmlspecialchars($clinicMeta, ENT_QUOTES, 'UTF-8')) . '</div>';
  $body .= '</div>';
  $body .= '</div>';
  $body .= '</div>';

  $body .= '<div class="section">';
  $body .= '<div class="h2">Billing Details</div>';
  $body .= '<table class="tbl"><tbody>';
  $body .= '<tr><th style="width:220px;">Service Type</th><td>' . htmlspecialchars($serviceType, ENT_QUOTES, 'UTF-8') . '</td></tr>';
  $body .= '<tr><th>Amount</th><td>' . htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') . '</td></tr>';
  $body .= '<tr><th>Payment Method</th><td>' . htmlspecialchars($paymentMethod !== '' ? $paymentMethod : '-', ENT_QUOTES, 'UTF-8') . '</td></tr>';
  $body .= '<tr><th>Payment Status</th><td>' . htmlspecialchars(ucfirst($paymentStatus), ENT_QUOTES, 'UTF-8') . '</td></tr>';
  $body .= '<tr><th>Notes</th><td>' . htmlspecialchars($notes !== '' ? $notes : '-', ENT_QUOTES, 'UTF-8') . '</td></tr>';
  $body .= '</tbody></table>';
  $body .= '</div>';

  $html = medikit_pdf_doc('Invoice #' . $invoiceNo, $body, $brand);
  $pdf = medikit_pdf_render_html($html);
  if (!is_string($pdf) || $pdf === '') {
    return null;
  }

  return [
    'filename' => 'Bill_' . $invoiceNo . '.pdf',
    'data' => $pdf,
  ];
}

function medikit_pdf_make_prescription_attachment(mysqli $conn, int $bookingId): ?array
{
  $rx = medikit_pdf_fetch_prescription_row($conn, $bookingId);
  if (!$rx) {
    return null;
  }

  $rxId = (int)($rx['id'] ?? 0);
  if ($rxId <= 0) {
    return null;
  }

  $rxCode = prescriptions_code($rxId);

  $doctorName = trim((string)($rx['doc_firstname'] ?? '') . ' ' . (string)($rx['doc_lastname'] ?? ''));
  $doctorName = $doctorName !== '' ? ('Dr. ' . $doctorName) : 'Doctor';

  $patientName = trim((string)($rx['patient_firstname'] ?? '') . ' ' . (string)($rx['patient_lastname'] ?? ''));
  $patientName = $patientName !== '' ? $patientName : 'Patient';

  $apptDate = !empty($rx['appointment_date']) ? date('F j, Y', strtotime((string)$rx['appointment_date'])) : '-';
  $startTime = (string)($rx['start_time'] ?? '');
  $endTime = (string)($rx['end_time'] ?? '');
  $apptTime = '-';
  if ($startTime !== '') {
    $apptTime = date('g:i A', strtotime($startTime));
    if ($endTime !== '') {
      $apptTime .= ' - ' . date('g:i A', strtotime($endTime));
    }
  }

  $clinicName = trim((string)($rx['clinic_name'] ?? ''));
  $clinicAddr = trim((string)($rx['clinic_address'] ?? ''));
  $clinicPhone = trim((string)($rx['clinic_phone'] ?? ''));
  $clinicEmail = trim((string)($rx['clinic_email'] ?? ''));

  $brand = $clinicName !== '' ? $clinicName : $doctorName;
  if (trim($brand) === '') {
    $brand = 'Clinic';
  }

  $diagnosis = trim((string)($rx['diagnosis'] ?? ''));
  $instructions = trim((string)($rx['instructions'] ?? ''));
  $medicationsRaw = (string)($rx['medications'] ?? '');

  $medItems = prescriptions_decode_medication_items($medicationsRaw);

  $body = '';
  $body .= '<div class="box">';
  $body .= '<div class="row">';
  $body .= '<div class="col"><div class="h3">Doctor</div>';
  $body .= '<div>' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '<div class="muted">' . htmlspecialchars((string)($rx['doctor_speciality'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '<div class="col"><div class="h3">Patient</div>';
  $body .= '<div>' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '</div>';

  $body .= '<div class="row" style="margin-top:10px;">';
  $body .= '<div class="col"><div class="h3">Appointment</div>';
  $body .= '<div>' . htmlspecialchars($apptDate, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '<div class="muted">' . htmlspecialchars($apptTime, ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';
  $body .= '<div class="col"><div class="h3">Clinic</div>';
  $body .= '<div>' . htmlspecialchars($clinicName !== '' ? $clinicName : '-', ENT_QUOTES, 'UTF-8') . '</div>';
  $clinicMeta = $clinicAddr !== '' ? $clinicAddr : '-';
  if ($clinicPhone !== '') {
    $clinicMeta .= "\nPhone: " . $clinicPhone;
  }
  if ($clinicEmail !== '') {
    $clinicMeta .= "\nEmail: " . $clinicEmail;
  }
  $body .= '<div class="muted">' . nl2br(htmlspecialchars($clinicMeta, ENT_QUOTES, 'UTF-8')) . '</div>';
  $body .= '</div>';
  $body .= '</div>';
  $body .= '</div>';

  $body .= '<div class="section">';
  $body .= '<div class="h2">Diagnosis</div>';
  $body .= '<div class="box white">' . htmlspecialchars($diagnosis !== '' ? $diagnosis : '-', ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';

  $body .= '<div class="section">';
  $body .= '<div class="h2">Medications</div>';

  if (is_array($medItems) && !empty($medItems)) {
    $body .= '<table class="tbl"><thead><tr>';
    $body .= '<th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Time</th>';
    $body .= '</tr></thead><tbody>';

    foreach ($medItems as $it) {
      $m = trim((string)($it['medicine'] ?? ''));
      $d = trim((string)($it['dosage'] ?? ''));
      $f = trim((string)($it['frequency'] ?? ''));
      $du = trim((string)($it['duration'] ?? ''));
      $t = trim((string)($it['time'] ?? ''));

      $body .= '<tr>';
      $body .= '<td>' . htmlspecialchars($m !== '' ? $m : '-', ENT_QUOTES, 'UTF-8') . '</td>';
      $body .= '<td>' . htmlspecialchars($d !== '' ? $d : '-', ENT_QUOTES, 'UTF-8') . '</td>';
      $body .= '<td>' . htmlspecialchars($f !== '' ? $f : '-', ENT_QUOTES, 'UTF-8') . '</td>';
      $body .= '<td>' . htmlspecialchars($du !== '' ? $du : '-', ENT_QUOTES, 'UTF-8') . '</td>';
      $body .= '<td>' . htmlspecialchars($t !== '' ? $t : '-', ENT_QUOTES, 'UTF-8') . '</td>';
      $body .= '</tr>';
    }

    $body .= '</tbody></table>';
  } else {
    $body .= '<div class="box white">' . htmlspecialchars(trim($medicationsRaw) !== '' ? $medicationsRaw : '-', ENT_QUOTES, 'UTF-8') . '</div>';
  }

  $body .= '</div>';

  $body .= '<div class="section">';
  $body .= '<div class="h2">Instructions</div>';
  $body .= '<div class="box white">' . htmlspecialchars($instructions !== '' ? $instructions : '-', ENT_QUOTES, 'UTF-8') . '</div>';
  $body .= '</div>';

  $html = medikit_pdf_doc('Prescription ' . $rxCode, $body, $brand);
  $pdf = medikit_pdf_render_html($html);
  if (!is_string($pdf) || $pdf === '') {
    return null;
  }

  return [
    'filename' => 'Prescription_' . $rxCode . '.pdf',
    'data' => $pdf,
  ];
}
