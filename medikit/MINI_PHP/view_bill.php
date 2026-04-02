<?php
session_start();
include("config.php");
include("billing_helpers.php");

if (!isset($_SESSION['patient_id'])) {
  header("Location: loginpatient.php");
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];

try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  $schema_error = $e->getMessage();
}

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$bill = null;

if (empty($schema_error) && ($bill_id > 0 || $booking_id > 0)) {
  if ($bill_id > 0) {
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
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_bills cb
            JOIN visit_booking vb ON vb.id = cb.booking_id
            JOIN users u ON u.id = cb.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cb.id = ? AND cb.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param("ii", $bill_id, $patient_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $bill = $res ? $res->fetch_assoc() : null;
      $stmt->close();
    }
  } else {
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
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_bills cb
            JOIN visit_booking vb ON vb.id = cb.booking_id
            JOIN users u ON u.id = cb.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cb.booking_id = ? AND cb.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param("ii", $booking_id, $patient_id);
      $stmt->execute();
      $res = $stmt->get_result();
      $bill = $res ? $res->fetch_assoc() : null;
      $stmt->close();
    }
  }
}

$invoice_no = $bill ? billing_invoice_no((int)$bill['bill_id']) : '';

$pay_status = $bill['payment_status'] ?? '';
$pay_badge = 'bg-warning text-dark';
$pay_text = 'Pending';
if ($pay_status === 'paid') {
  $pay_badge = 'bg-success';
  $pay_text = 'Paid';
}

$appt_date = '-';
$appt_time = '-';
if (!empty($bill['appointment_date'])) {
  $appt_date = date("F j, Y", strtotime((string)$bill['appointment_date']));
}
if (!empty($bill['start_time'])) {
  $appt_time = date("g:i A", strtotime((string)$bill['start_time']));
}

$clinic_name = trim((string)($bill['clinic_name'] ?? ''));
$clinic_address = trim((string)($bill['clinic_address'] ?? ''));
$clinic_phone = trim((string)($bill['clinic_phone'] ?? ''));
$clinic_email = trim((string)($bill['clinic_email'] ?? ''));
?>

<?php include('header.php'); ?>

<main class="container my-5">
  <?php if (!empty($schema_error)): ?>
    <div class="alert alert-danger">Billing table error: <?= htmlspecialchars($schema_error) ?></div>
  <?php elseif (!$bill): ?>
    <div class="card text-center py-5">
      <div class="card-body">
        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
        <h4 class="fw-bold">Bill Not Found</h4>
        <p class="text-muted">This bill is not available.</p>
        <a href="my_appointments.php" class="btn btn-primary mt-2">Back to My Appointments</a>
      </div>
    </div>
  <?php else: ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 12px;">
              <div>
                <div class="text-muted">Invoice</div>
                <h4 class="fw-bold mb-1">#<?= htmlspecialchars($invoice_no) ?></h4>
                <div class="text-muted small">Payment is done at clinic.</div>
              </div>
              <div class="text-end">
                <span class="badge rounded-pill fs-6 <?= htmlspecialchars($pay_badge) ?>"><?= htmlspecialchars($pay_text) ?></span>
                <div class="text-muted small mt-2">Created: <?= htmlspecialchars(date("F j, Y", strtotime((string)$bill['created_at']))) ?></div>
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-bold mb-1">Doctor</div>
                <div>Dr. <?= htmlspecialchars((string)$bill['doc_firstname'] . ' ' . (string)$bill['doc_lastname']) ?></div>
                <div class="text-primary fw-bold"><?= htmlspecialchars((string)($bill['doctor_speciality'] ?? '')) ?></div>
              </div>
              <div class="col-md-6">
                <div class="fw-bold mb-1">Appointment</div>
                <div class="text-muted"><i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($appt_date) ?></div>
                <div class="text-muted"><i class="fas fa-clock me-2"></i><?= htmlspecialchars($appt_time) ?></div>
              </div>
            </div>

            <div class="row g-3 mt-2">
              <div class="col-md-12">
                <div class="fw-bold mb-1">Clinic Info</div>
                <div class="text-muted">
                  <?php if ($clinic_name !== ''): ?>
                    <div><strong>Clinic:</strong> <?= htmlspecialchars($clinic_name) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_address !== ''): ?>
                    <div><strong>Address:</strong> <?= htmlspecialchars($clinic_address) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_phone !== ''): ?>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($clinic_phone) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_email !== ''): ?>
                    <div><strong>Email:</strong> <?= htmlspecialchars($clinic_email) ?></div>
                  <?php endif; ?>
                  <?php if ($clinic_name === '' && $clinic_address === '' && $clinic_phone === '' && $clinic_email === ''): ?>
                    <div>-</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="table-responsive">
              <table class="table">
                <tbody>
                  <tr>
                    <th style="width: 220px;">Service Type</th>
                    <td><?= htmlspecialchars((string)($bill['service_type'] ?? 'Consultation')) ?></td>
                  </tr>
                  <tr>
                    <th>Amount</th>
                    <td><?= htmlspecialchars(number_format((float)($bill['amount'] ?? 0), 2)) ?></td>
                  </tr>
                  <tr>
                    <th>Payment Method</th>
                    <td><?= htmlspecialchars((string)($bill['payment_method'] ?? '')) ?></td>
                  </tr>
                  <tr>
                    <th>Notes</th>
                    <td><?= htmlspecialchars((string)($bill['Note'] ?? '-')) ?></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <a href="my_appointments.php" class="btn btn-outline-primary">Back</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include('footer.php'); ?>