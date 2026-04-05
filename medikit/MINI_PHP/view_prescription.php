<?php
session_start();
include("config.php");
include("prescription_helpers.php");

if (!isset($_SESSION['patient_id'])) {
  header("Location: loginpatient.php");
  exit;
}

$patient_id = (int)$_SESSION['patient_id'];

$schema_error = '';
try {
  prescriptions_ensure_schema($conn);
} catch (Throwable $e) {
  $schema_error = $e->getMessage();
}

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$prescription_id = isset($_GET['prescription_id']) ? (int)$_GET['prescription_id'] : 0;

$rx = null;
if ($schema_error === '' && ($booking_id > 0 || $prescription_id > 0)) {
  if ($prescription_id > 0) {
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
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_prescriptions cp
            LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
            LEFT JOIN users u ON u.id = cp.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cp.id = ? AND cp.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param('ii', $prescription_id, $patient_id);
      $stmt->execute();
      $rx = $stmt->get_result()->fetch_assoc();
      $stmt->close();
    }
  } else {
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
                s.doctor_speciality,
                dat.start_time,
                dat.end_time
            FROM clinic_prescriptions cp
            LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
            LEFT JOIN users u ON u.id = cp.doctor_id
            LEFT JOIN speciality s ON s.id = vb.speciality_id
            LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
            WHERE cp.booking_id = ? AND cp.patient_id = ?
            LIMIT 1
        ";

    $stmt = $conn->prepare($q);
    if ($stmt) {
      $stmt->bind_param('ii', $booking_id, $patient_id);
      $stmt->execute();
      $rx = $stmt->get_result()->fetch_assoc();
      $stmt->close();
    }
  }
}

$rx_id = $rx ? (int)$rx['id'] : 0;
$rx_code = $rx_id > 0 ? prescriptions_code($rx_id) : '';

$appt_date = '-';
$appt_time = '-';
if (!empty($rx['appointment_date'])) {
  $appt_date = date('F j, Y', strtotime((string)$rx['appointment_date']));
}
if (!empty($rx['start_time'])) {
  $appt_time = date('g:i A', strtotime((string)$rx['start_time']));
}

$clinic_name = trim((string)($rx['clinic_name'] ?? ''));
$clinic_address = trim((string)($rx['clinic_address'] ?? ''));
$clinic_phone = trim((string)($rx['clinic_phone'] ?? ''));
$clinic_email = trim((string)($rx['clinic_email'] ?? ''));

$med_items = null;
if ($rx) {
  $med_items = prescriptions_decode_medication_items((string)($rx['medications'] ?? ''));
}
?>

<?php include('header.php'); ?>

<style>
  .rx-pre {
    white-space: pre-wrap;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    font-size: 14px;
    color: #111827;
  }

  @media print {

    nav.navbar,
    footer,
    .page-header,
    .rx-actions {
      display: none !important;
    }

    body {
      background: #fff !important;
    }

    .card {
      box-shadow: none !important;
      border: 1px solid #e5e7eb !important;
    }
  }
</style>

<main class="container my-5">
  <?php if (!empty($schema_error)): ?>
    <div class="alert alert-danger">Prescriptions are not available right now.</div>
  <?php elseif (!$rx): ?>
    <div class="card text-center py-5">
      <div class="card-body">
        <i class="fas fa-file-prescription fa-3x text-muted mb-3"></i>
        <h4 class="fw-bold">Prescription Not Found</h4>
        <p class="text-muted">This prescription is not available.</p>
        <a href="my_prescriptions.php" class="btn btn-primary mt-2">Back to My Prescriptions</a>
      </div>
    </div>
  <?php else: ?>
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card" id="rx-print-area">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 12px;">
              <div>
                <div class="text-muted">Prescription</div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($rx_code) ?></h4>
                <div class="text-muted small">Created: <?= htmlspecialchars(date('F j, Y', strtotime((string)$rx['created_at']))) ?></div>
              </div>
              <div class="text-end">
                <span class="badge rounded-pill fs-6 <?= ($rx['status'] ?? '') === 'inactive' ? 'bg-secondary' : 'bg-success' ?>"><?= htmlspecialchars(ucfirst((string)($rx['status'] ?? 'active'))) ?></span>
              </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-bold mb-1">Doctor</div>
                <div>Dr. <?= htmlspecialchars((string)($rx['doc_firstname'] ?? '') . ' ' . (string)($rx['doc_lastname'] ?? '')) ?></div>
                <div class="text-primary fw-bold"><?= htmlspecialchars((string)($rx['doctor_speciality'] ?? '')) ?></div>
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

            <div class="row g-3">
              <div class="col-md-6">
                <div class="fw-bold mb-1">Diagnosis</div>
                <div><?= htmlspecialchars((string)($rx['diagnosis'] ?? '')) ?></div>
              </div>
              <div class="col-md-6">
                <div class="fw-bold mb-1">Schedule</div>
                <div class="text-muted">
                  <?php if (is_array($med_items) && !empty($med_items)): ?>
                    <div>See medicine schedule below.</div>
                  <?php else: ?>
                    <div><strong>Dosage:</strong> <?= htmlspecialchars((string)($rx['dosage'] ?? '')) ?></div>
                    <div><strong>Frequency:</strong> <?= htmlspecialchars((string)($rx['frequency'] ?? '')) ?></div>
                    <div><strong>Duration:</strong> <?= htmlspecialchars((string)($rx['duration'] ?? '')) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="col-12">
                <div class="fw-bold mb-1">Medications</div>
                <?php if (is_array($med_items) && !empty($med_items)): ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          <th>Medicine</th>
                          <th>Dosage</th>
                          <th>Frequency</th>
                          <th>Duration</th>
                          <th>Time</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($med_items as $it): ?>
                          <?php
                          $m = trim((string)($it['medicine'] ?? ''));
                          $d = trim((string)($it['dosage'] ?? ''));
                          $f = trim((string)($it['frequency'] ?? ''));
                          $du = trim((string)($it['duration'] ?? ''));
                          $t = trim((string)($it['time'] ?? ''));
                          ?>
                          <tr>
                            <td><?= htmlspecialchars($m !== '' ? $m : '-') ?></td>
                            <td><?= htmlspecialchars($d !== '' ? $d : '-') ?></td>
                            <td><?= htmlspecialchars($f !== '' ? $f : '-') ?></td>
                            <td><?= htmlspecialchars($du !== '' ? $du : '-') ?></td>
                            <td><?= htmlspecialchars($t !== '' ? $t : '-') ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="rx-pre"><?= htmlspecialchars((string)($rx['medications'] ?? '')) ?></div>
                <?php endif; ?>
              </div>

              <div class="col-12">
                <div class="fw-bold mb-1">Instructions</div>
                <div class="rx-pre"><?= htmlspecialchars((string)($rx['instructions'] ?? '')) ?></div>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 rx-actions">
              <a href="my_prescriptions.php" class="btn btn-outline-primary">Back</a>
              <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fas fa-download me-1"></i> Download</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php include('footer.php'); ?>