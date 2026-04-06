<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$commission_rate = medikit_fixed_commission_percent();
$now_label = date('F j, Y g:i A');

$doctorStats = [
  'total' => 0,
  'pending' => 0,
  'verified' => 0,
  'rejected' => 0,
];
$row = null;
$res = $conn->query(
  "SELECT\n      COUNT(*) AS total,\n      COALESCE(SUM(verification_status = 'pending'), 0) AS pending,\n      COALESCE(SUM(verification_status = 'verified'), 0) AS verified,\n      COALESCE(SUM(verification_status = 'rejected'), 0) AS rejected\n     FROM users\n    WHERE role_id = 2"
);
if ($res) {
  $row = $res->fetch_assoc();
  $doctorStats['total'] = (int)($row['total'] ?? 0);
  $doctorStats['pending'] = (int)($row['pending'] ?? 0);
  $doctorStats['verified'] = (int)($row['verified'] ?? 0);
  $doctorStats['rejected'] = (int)($row['rejected'] ?? 0);
}

$patient_total = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM patient");
if ($res) {
  $patient_total = (int)($res->fetch_assoc()['total'] ?? 0);
}

$bookingStats = [
  'total' => 0,
  'pending' => 0,
  'accepted' => 0,
  'visited' => 0,
  'rejected' => 0,
];
$res = $conn->query(
  "SELECT\n      COUNT(*) AS total,\n      COALESCE(SUM(status = 'pending'), 0) AS pending,\n      COALESCE(SUM(status = 'accepted'), 0) AS accepted,\n      COALESCE(SUM(status = 'visited'), 0) AS visited,\n      COALESCE(SUM(status = 'rejected'), 0) AS rejected\n     FROM visit_booking"
);
if ($res) {
  $row = $res->fetch_assoc();
  $bookingStats['total'] = (int)($row['total'] ?? 0);
  $bookingStats['pending'] = (int)($row['pending'] ?? 0);
  $bookingStats['accepted'] = (int)($row['accepted'] ?? 0);
  $bookingStats['visited'] = (int)($row['visited'] ?? 0);
  $bookingStats['rejected'] = (int)($row['rejected'] ?? 0);
}

$billPaidStats = [
  'count' => 0,
  'total' => 0.0,
];
$res = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM clinic_bills WHERE payment_status = 'paid'");
if ($res) {
  $row = $res->fetch_assoc();
  $billPaidStats['count'] = (int)($row['cnt'] ?? 0);
  $billPaidStats['total'] = (float)($row['total'] ?? 0);
}

$billVisitedPaidStats = [
  'count' => 0,
  'total' => 0.0,
];
$res = $conn->query(
  "SELECT COUNT(*) AS cnt, COALESCE(SUM(cb.amount), 0) AS total\n     FROM clinic_bills cb\n     INNER JOIN visit_booking vb ON vb.id = cb.booking_id\n    WHERE cb.payment_status = 'paid'\n      AND vb.status = 'visited'"
);
if ($res) {
  $row = $res->fetch_assoc();
  $billVisitedPaidStats['count'] = (int)($row['cnt'] ?? 0);
  $billVisitedPaidStats['total'] = (float)($row['total'] ?? 0);
}

$commissionStats = [
  'due_total' => 0.0,
  'paid_total' => 0.0,
  'due_count' => 0,
  'paid_count' => 0,
];
$res = $conn->query(
  "SELECT\n      COALESCE(SUM(CASE WHEN dc.status = 'due' THEN dc.commission_amount ELSE 0 END), 0) AS due_total,\n      COALESCE(SUM(CASE WHEN dc.status = 'paid' THEN dc.commission_amount ELSE 0 END), 0) AS paid_total,\n      COALESCE(SUM(dc.status = 'due'), 0) AS due_count,\n      COALESCE(SUM(dc.status = 'paid'), 0) AS paid_count\n     FROM doctor_commissions dc\n     INNER JOIN clinic_bills cb ON cb.id = dc.bill_id AND cb.payment_status = 'paid'\n     INNER JOIN visit_booking vb ON vb.id = dc.booking_id AND vb.status = 'visited'"
);
if ($res) {
  $row = $res->fetch_assoc();
  $commissionStats['due_total'] = (float)($row['due_total'] ?? 0);
  $commissionStats['paid_total'] = (float)($row['paid_total'] ?? 0);
  $commissionStats['due_count'] = (int)($row['due_count'] ?? 0);
  $commissionStats['paid_count'] = (int)($row['paid_count'] ?? 0);
}

$commission_due_total = $commissionStats['due_total'];
$commission_paid_total = $commissionStats['paid_total'];
$commission_total = $commission_due_total + $commission_paid_total;

$recent_commissions = [];
$res = $conn->query(
  "SELECT\n      dc.id, dc.booking_id, dc.bill_id, dc.amount, dc.commission_amount, dc.status, dc.created_at,\n      u.firstname, u.lastname\n     FROM doctor_commissions dc\n     INNER JOIN clinic_bills cb ON cb.id = dc.bill_id AND cb.payment_status = 'paid'\n     INNER JOIN visit_booking vb ON vb.id = dc.booking_id AND vb.status = 'visited'\n     LEFT JOIN users u ON u.id = dc.doctor_id\n    ORDER BY (dc.status = 'due') DESC, dc.created_at DESC, dc.id DESC\n    LIMIT 8"
);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $recent_commissions[] = $r;
  }
}

$pending_list = [];
$res = $conn->query(
  "SELECT id, firstname, lastname, clinic_name, created_at\n     FROM users\n    WHERE role_id = 2 AND verification_status = 'pending'\n    ORDER BY created_at DESC, id DESC\n    LIMIT 5"
);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $pending_list[] = $r;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Medkit</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../custom_style.css?v=<?php echo $css_v; ?>">
</head>

<body style="background-color: var(--secondary-color);">
  <nav class="navbar navbar-expand-lg bg-white py-3 shadow-sm">
    <div class="container">
      <a class="navbar-brand text-primary" href="dashboard.php"><i class="fas fa-shield-halved me-2"></i>Medkit Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="doctors.php">Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="patients.php">Patients</a></li>
          <li class="nav-item"><a class="nav-link" href="verify_doctors.php">Verify Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="commissions.php">Commissions</a></li>
          <li class="nav-item"><a class="nav-link" href="contact_messages.php">Messages</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_users.php">Admins</a></li>
        </ul>
        <div class="d-flex align-items-center gap-3">
          <span class="text-muted small"><i class="fas fa-user me-1"></i><?php echo admin_h(admin_name()); ?></span>
          <a class="btn btn-outline-primary btn-sm" href="logout.php">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="container py-5">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-4">
      <div>
        <h3 class="fw-bold mb-1">Dashboard</h3>
        <div class="text-muted small">A quick overview of operations and finance.</div>
      </div>
      <div class="text-end">
        <div class="badge bg-primary">Commission: <?php echo number_format($commission_rate, 2); ?>% fixed</div>
        <div class="text-muted small mt-1">Last updated: <?php echo admin_h($now_label); ?></div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Total Doctors</div>
                <div class="fs-3 fw-bold text-primary"><?php echo (int)$doctorStats['total']; ?></div>
                <div class="small text-muted">All registered doctors</div>
              </div>
              <div class="bg-primary-subtle text-primary rounded-3 p-2">
                <i class="fas fa-user-doctor fs-4"></i>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
              <span class="badge bg-success">Verified: <?php echo (int)$doctorStats['verified']; ?></span>
              <span class="badge bg-warning text-dark">Pending: <?php echo (int)$doctorStats['pending']; ?></span>
              <span class="badge bg-danger">Rejected: <?php echo (int)$doctorStats['rejected']; ?></span>
            </div>
            <a href="doctors.php" class="btn btn-sm btn-outline-primary mt-3">Manage</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Pending Verification</div>
                <div class="fs-3 fw-bold text-warning"><?php echo (int)$doctorStats['pending']; ?></div>
                <div class="small text-muted">License review required</div>
              </div>
              <div class="bg-warning-subtle text-warning rounded-3 p-2">
                <i class="fas fa-user-clock fs-4"></i>
              </div>
            </div>
            <a href="verify_doctors.php" class="btn btn-sm btn-primary mt-3">Review</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Total Patients</div>
                <div class="fs-3 fw-bold text-success"><?php echo (int)$patient_total; ?></div>
                <div class="small text-muted">Registered patients</div>
              </div>
              <div class="bg-success-subtle text-success rounded-3 p-2">
                <i class="fas fa-users fs-4"></i>
              </div>
            </div>
            <a href="patients.php" class="btn btn-sm btn-outline-primary mt-3">View</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Appointments (Visited)</div>
                <div class="fs-3 fw-bold text-secondary"><?php echo (int)$bookingStats['visited']; ?></div>
                <div class="small text-muted">Total: <?php echo (int)$bookingStats['total']; ?></div>
              </div>
              <div class="bg-secondary-subtle text-secondary rounded-3 p-2">
                <i class="fas fa-calendar-check fs-4"></i>
              </div>
            </div>
            <div class="mt-3 small text-muted">
              Pending: <strong><?php echo (int)$bookingStats['pending']; ?></strong> · Accepted: <strong><?php echo (int)$bookingStats['accepted']; ?></strong> · Rejected: <strong><?php echo (int)$bookingStats['rejected']; ?></strong>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Bills Paid (All)</div>
                <div class="fs-4 fw-bold text-primary">₹ <?php echo number_format($billPaidStats['total'], 2); ?></div>
                <div class="small text-muted">Transactions: <?php echo (int)$billPaidStats['count']; ?></div>
              </div>
              <div class="bg-primary-subtle text-primary rounded-3 p-2">
                <i class="fas fa-file-invoice-dollar fs-4"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Paid &amp; Visited Bills</div>
                <div class="fs-4 fw-bold text-success">₹ <?php echo number_format($billVisitedPaidStats['total'], 2); ?></div>
                <div class="small text-muted">Eligible: <?php echo (int)$billVisitedPaidStats['count']; ?> bills</div>
              </div>
              <div class="bg-success-subtle text-success rounded-3 p-2">
                <i class="fas fa-circle-check fs-4"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Commission Due</div>
                <div class="fs-4 fw-bold text-danger">₹ <?php echo number_format($commission_due_total, 2); ?></div>
                <div class="small text-muted">Due items: <?php echo (int)$commissionStats['due_count']; ?></div>
              </div>
              <div class="bg-danger-subtle text-danger rounded-3 p-2">
                <i class="fas fa-hand-holding-dollar fs-4"></i>
              </div>
            </div>
            <a href="commissions.php" class="btn btn-sm btn-outline-danger mt-3">View</a>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="text-muted small">Commission Collected (Profit)</div>
                <div class="fs-4 fw-bold text-secondary">₹ <?php echo number_format($commission_paid_total, 2); ?></div>
                <div class="small text-muted">Paid items: <?php echo (int)$commissionStats['paid_count']; ?></div>
              </div>
              <div class="bg-secondary-subtle text-secondary rounded-3 p-2">
                <i class="fas fa-wallet fs-4"></i>
              </div>
            </div>
            <div class="mt-3 small text-muted">Total commission generated: <strong>₹ <?php echo number_format($commission_total, 2); ?></strong></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Appointments Breakdown</h6>
            <div class="text-muted small mb-3">All-time booking status summary.</div>
            <div class="d-flex flex-column gap-2">
              <div class="d-flex justify-content-between"><span class="small text-muted">Pending</span><span class="fw-semibold"><?php echo (int)$bookingStats['pending']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Accepted</span><span class="fw-semibold"><?php echo (int)$bookingStats['accepted']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Visited</span><span class="fw-semibold"><?php echo (int)$bookingStats['visited']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Rejected</span><span class="fw-semibold"><?php echo (int)$bookingStats['rejected']; ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Doctors Breakdown</h6>
            <div class="text-muted small mb-3">Verification status summary.</div>
            <div class="d-flex flex-column gap-2">
              <div class="d-flex justify-content-between"><span class="small text-muted">Total</span><span class="fw-semibold"><?php echo (int)$doctorStats['total']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Verified</span><span class="fw-semibold text-success"><?php echo (int)$doctorStats['verified']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Pending</span><span class="fw-semibold text-warning"><?php echo (int)$doctorStats['pending']; ?></span></div>
              <div class="d-flex justify-content-between"><span class="small text-muted">Rejected</span><span class="fw-semibold text-danger"><?php echo (int)$doctorStats['rejected']; ?></span></div>
            </div>
            <div class="mt-3">
              <a href="verify_doctors.php" class="btn btn-sm btn-outline-primary">Verify Doctors</a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h6 class="fw-bold mb-1">Quick Actions</h6>
            <div class="text-muted small mb-3">Common admin shortcuts.</div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-outline-primary btn-sm" href="doctors.php"><i class="fas fa-user-doctor me-1"></i>Doctors</a>
              <a class="btn btn-outline-primary btn-sm" href="patients.php"><i class="fas fa-users me-1"></i>Patients</a>
              <a class="btn btn-primary btn-sm" href="verify_doctors.php"><i class="fas fa-id-card me-1"></i>Verify</a>
              <a class="btn btn-outline-primary btn-sm" href="commissions.php"><i class="fas fa-receipt me-1"></i>Commissions</a>
              <a class="btn btn-outline-primary btn-sm" href="admin_users.php"><i class="fas fa-user-shield me-1"></i>Admins</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h6 class="fw-bold mb-0">Recent Commissions</h6>
                <div class="text-muted small">Latest visited + paid commission items.</div>
              </div>
              <a href="commissions.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Doctor</th>
                  <th>Booking</th>
                  <th class="text-end">Bill</th>
                  <th class="text-end">Commission</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_commissions)): ?>
                  <tr>
                    <td colspan="6" class="text-center text-muted py-4">No commission items yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_commissions as $it): ?>
                    <?php
                    $doc = trim('Dr. ' . (string)($it['firstname'] ?? '') . ' ' . (string)($it['lastname'] ?? ''));
                    if ($doc === 'Dr.') {
                      $doc = 'Doctor';
                    }
                    $st = (string)($it['status'] ?? 'due');
                    $badge = ($st === 'paid') ? 'success' : 'warning';
                    ?>
                    <tr>
                      <td class="fw-semibold"><?php echo admin_h($doc); ?></td>
                      <td class="small text-muted">#<?php echo (int)($it['booking_id'] ?? 0); ?> <span class="text-muted">(Bill #<?php echo (int)($it['bill_id'] ?? 0); ?>)</span></td>
                      <td class="text-end">₹ <?php echo number_format((float)($it['amount'] ?? 0), 2); ?></td>
                      <td class="text-end fw-bold">₹ <?php echo number_format((float)($it['commission_amount'] ?? 0), 2); ?></td>
                      <td><span class="badge bg-<?php echo $badge; ?> text-uppercase"><?php echo admin_h($st); ?></span></td>
                      <td class="small text-muted"><?php echo admin_h((string)($it['created_at'] ?? '')); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <h6 class="fw-bold mb-0">Pending Doctors</h6>
                <div class="text-muted small">Newest requests waiting for verification.</div>
              </div>
              <a href="verify_doctors.php" class="btn btn-sm btn-outline-primary">Open</a>
            </div>
          </div>
          <div class="list-group list-group-flush">
            <?php if (empty($pending_list)): ?>
              <div class="p-4 text-center text-muted">No pending doctors.</div>
            <?php else: ?>
              <?php foreach ($pending_list as $d): ?>
                <?php
                $name = trim((string)($d['firstname'] ?? '') . ' ' . (string)($d['lastname'] ?? ''));
                $clinic = (string)($d['clinic_name'] ?? '');
                ?>
                <div class="list-group-item">
                  <div class="fw-semibold">Dr. <?php echo admin_h($name !== '' ? $name : '—'); ?></div>
                  <div class="small text-muted d-flex justify-content-between gap-2">
                    <span><?php echo admin_h($clinic !== '' ? $clinic : '—'); ?></span>
                    <span><?php echo admin_h((string)($d['created_at'] ?? '')); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>