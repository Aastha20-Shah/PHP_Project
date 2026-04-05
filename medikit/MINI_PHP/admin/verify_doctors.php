<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doctor_id'], $_POST['action'])) {
  $doctor_id = (int)($_POST['doctor_id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');

  if ($doctor_id <= 0) {
    $msg = 'Invalid doctor.';
    $msg_type = 'danger';
  } elseif ($action === 'verify') {
    // Require license details before verification.
    $license_ok = false;
    $stmtLic = $conn->prepare("SELECT license_number, license_document FROM users WHERE id = ? AND role_id = 2 LIMIT 1");
    if ($stmtLic) {
      $stmtLic->bind_param('i', $doctor_id);
      $stmtLic->execute();
      $licRow = $stmtLic->get_result()->fetch_assoc();
      $stmtLic->close();
      $license_no = trim((string)($licRow['license_number'] ?? ''));
      $license_doc = trim((string)($licRow['license_document'] ?? ''));
      $license_ok = ($license_no !== '' && $license_doc !== '');
    }

    if (!$license_ok) {
      $msg = 'Cannot verify doctor: license number and document are required.';
      $msg_type = 'danger';
    } else {
      $commission_percent = medikit_fixed_commission_percent();
      $admin_id = (int)$_SESSION['admin_id'];
      $stmt = $conn->prepare("UPDATE users
              SET verification_status = 'verified',
                  verified_at = NOW(),
                  verified_by_admin_id = ?,
                  verification_reason = NULL,
                  commission_percent = ?
              WHERE id = ? AND role_id = 2");
      if ($stmt) {
        $stmt->bind_param('idi', $admin_id, $commission_percent, $doctor_id);
        $stmt->execute();
        $stmt->close();
        $msg = 'Doctor verified successfully.';
        $msg_type = 'success';
      }
    }
  } elseif ($action === 'reject') {
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
      $reason = 'Rejected by admin.';
    }
    if (strlen($reason) > 255) {
      $reason = substr($reason, 0, 255);
    }

    $admin_id = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare("UPDATE users
            SET verification_status = 'rejected',
                verified_at = NOW(),
                verified_by_admin_id = ?,
                verification_reason = ?
            WHERE id = ? AND role_id = 2");
    if ($stmt) {
      $stmt->bind_param('isi', $admin_id, $reason, $doctor_id);
      $stmt->execute();
      $stmt->close();
      $msg = 'Doctor rejected.';
      $msg_type = 'warning';
    }
  }

  header('Location: verify_doctors.php?msg=' . urlencode($msg) . '&type=' . urlencode($msg_type));
  exit;
}

if (isset($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
  $msg_type = (string)($_GET['type'] ?? 'success');
  if (!in_array($msg_type, ['success', 'danger', 'warning', 'info'], true)) {
    $msg_type = 'success';
  }
}

$pending = [];
$q = "SELECT id, firstname, lastname, email, phone_number, address, clinic_name, license_number, license_document, created_at
      FROM users
      WHERE role_id = 2 AND verification_status = 'pending'
      ORDER BY created_at DESC, id DESC";
$res = $conn->query($q);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $pending[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Doctors | Admin</title>

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
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="doctors.php">Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="patients.php">Patients</a></li>
          <li class="nav-item"><a class="nav-link active" href="verify_doctors.php">Verify Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="commissions.php">Commissions</a></li>
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
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h3 class="fw-bold mb-0">Pending Doctor Verification</h3>
      <span class="badge bg-primary"><?php echo count($pending); ?></span>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <?php if (empty($pending)): ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
          <i class="fas fa-user-check fs-1 text-success mb-3"></i>
          <h5 class="fw-bold">No pending doctors</h5>
          <p class="text-muted mb-0">All new doctor registrations are verified.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Doctor</th>
                <th>Contact</th>
                <th>License</th>
                <th style="min-width: 280px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $d): ?>
                <?php
                $doctor_id = (int)($d['id'] ?? 0);
                $name = trim((string)($d['firstname'] ?? '') . ' ' . (string)($d['lastname'] ?? ''));
                $email = (string)($d['email'] ?? '');
                $phone = (string)($d['phone_number'] ?? '');
                $license_no = (string)($d['license_number'] ?? '');
                $has_doc = (string)($d['license_document'] ?? '') !== '';
                ?>
                <tr>
                  <td>
                    <div class="fw-bold">Dr. <?php echo admin_h($name !== '' ? $name : '—'); ?></div>
                    <div class="text-muted small"><?php echo admin_h((string)($d['clinic_name'] ?? '')); ?></div>
                  </td>
                  <td>
                    <div class="small"><i class="fas fa-envelope me-1"></i><?php echo admin_h($email); ?></div>
                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo admin_h($phone); ?></div>
                  </td>
                  <td>
                    <div class="small"><strong>No:</strong> <?php echo admin_h($license_no !== '' ? $license_no : '—'); ?></div>
                    <div class="small">
                      <?php if ($has_doc): ?>
                        <a href="license.php?doctor_id=<?php echo $doctor_id; ?>" target="_blank" class="text-primary text-decoration-none">
                          <i class="fas fa-file-lines me-1"></i>View Document
                        </a>
                      <?php else: ?>
                        <span class="text-danger small">No document</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td>
                    <form method="POST" class="d-flex flex-wrap gap-2 align-items-end">
                      <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">

                      <div class="small text-muted mb-1">Commission: <?php echo number_format(medikit_fixed_commission_percent(), 2); ?>% (fixed)</div>

                      <button type="submit" name="action" value="verify" class="btn btn-sm btn-success">
                        <i class="fas fa-check me-1"></i>Verify
                      </button>

                      <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-<?php echo $doctor_id; ?>">
                        <i class="fas fa-xmark me-1"></i>Reject
                      </button>

                      <div class="collapse w-100" id="reject-<?php echo $doctor_id; ?>">
                        <div class="mt-2">
                          <label class="form-label small mb-1">Reason (optional)</label>
                          <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g., License mismatch">
                          <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger mt-2">
                            Confirm Reject
                          </button>
                        </div>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>