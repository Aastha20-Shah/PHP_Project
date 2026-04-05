<?php
session_start();
include("config.php");
include("prescription_helpers.php");
include("billing_helpers.php");

if (!isset($_SESSION['patient_id'])) {
  header("Location: loginpatient.php");
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];

$rx_schema_error = '';
try {
  prescriptions_ensure_schema($conn);
} catch (Throwable $e) {
  $rx_schema_error = $e->getMessage();
}

$billing_schema_error = '';
try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  $billing_schema_error = $e->getMessage();
}

$rows = [];
if ($rx_schema_error === '') {
  $q = "
        SELECT
            cp.id AS prescription_id,
            cp.booking_id,
            cp.status AS rx_status,
            cp.created_at AS rx_created_at,
            vb.appointment_date,
            u.firstname AS doc_firstname,
            u.lastname AS doc_lastname,
            u.clinic_name,
            u.address AS clinic_address,
            u.phone_number AS clinic_phone,
            u.email AS clinic_email,
            s.doctor_speciality,
            cb.id AS bill_id,
            cb.payment_status AS bill_status
        FROM clinic_prescriptions cp
        LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
        LEFT JOIN users u ON u.id = cp.doctor_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN clinic_bills cb ON cb.booking_id = cp.booking_id
        WHERE cp.patient_id = ?
        ORDER BY cp.created_at DESC, cp.id DESC
    ";

  $stmt = $conn->prepare($q);
  if ($stmt) {
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
      $rows[] = $r;
    }
    $stmt->close();
  }
}
?>

<?php include('header.php'); ?>

<main class="container my-5">
  <?php if ($rx_schema_error !== ''): ?>
    <div class="alert alert-danger">Prescriptions are not available right now.</div>
  <?php endif; ?>

  <?php if (empty($rows) && $rx_schema_error === ''): ?>
    <div class="card text-center py-5">
      <div class="card-body">
        <i class="fas fa-file-prescription fa-3x text-muted mb-3"></i>
        <h4 class="fw-bold">No Prescriptions Found</h4>
        <p class="text-muted">Your prescriptions will appear here after your appointment is completed.</p>
        <a href="my_appointments.php" class="btn btn-primary mt-2">Go to My Appointments</a>
      </div>
    </div>
  <?php elseif (!empty($rows)): ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <?php foreach ($rows as $r): ?>
          <?php
          $rx_id = (int)($r['prescription_id'] ?? 0);
          $rx_code = $rx_id > 0 ? prescriptions_code($rx_id) : '-';

          $date_label = '-';
          if (!empty($r['appointment_date'])) {
            $date_label = date('F j, Y', strtotime((string)$r['appointment_date']));
          }

          $status = (string)($r['rx_status'] ?? 'active');
          $status_badge = $status === 'inactive' ? 'bg-secondary' : 'bg-success';

          $bill_id = (int)($r['bill_id'] ?? 0);
          $bill_status = (string)($r['bill_status'] ?? '');
          $bill_badge = 'bg-warning text-dark';
          $bill_text = 'Pending';
          if ($bill_status === 'paid') {
            $bill_badge = 'bg-success';
            $bill_text = 'Paid';
          }
          ?>

          <div class="card mb-3">
            <div class="card-body p-4">
              <div class="row align-items-center">
                <div class="col-md-7">
                  <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($rx_code) ?></h5>
                    <span class="badge rounded-pill <?= htmlspecialchars($status_badge) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                  </div>

                  <div class="mt-2">
                    <div class="fw-bold">Dr. <?= htmlspecialchars((string)($r['doc_firstname'] ?? '') . ' ' . (string)($r['doc_lastname'] ?? '')) ?></div>
                    <div class="text-primary fw-bold"><?= htmlspecialchars((string)($r['doctor_speciality'] ?? '')) ?></div>
                    <div class="text-muted small mt-1"><i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($date_label) ?></div>
                  </div>
                </div>

                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                  <?php if ($bill_id > 0): ?>
                    <div class="mb-2">
                      <span class="badge rounded-pill <?= htmlspecialchars($bill_badge) ?>">Bill: <?= htmlspecialchars($bill_text) ?></span>
                    </div>
                  <?php endif; ?>

                  <div class="d-flex flex-wrap justify-content-md-end" style="gap: 10px;">
                    <a href="view_prescription.php?booking_id=<?= (int)$r['booking_id'] ?>" class="btn btn-outline-dark btn-sm">View / Download</a>
                    <?php if ($bill_id > 0): ?>
                      <a href="view_bill.php?bill_id=<?= $bill_id ?>" class="btn btn-outline-primary btn-sm">Bill</a>
                    <?php else: ?>
                      <a href="view_bill.php?booking_id=<?= (int)$r['booking_id'] ?>" class="btn btn-outline-primary btn-sm">Bill</a>
                    <?php endif; ?>
                  </div>

                  <?php if ($billing_schema_error !== '' && $bill_id > 0): ?>
                    <div class="text-muted small mt-2">Bill download is not available right now.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include('footer.php'); ?>