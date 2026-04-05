<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$msg = '';
$msg_type = 'success';
if (isset($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
  $msg_type = (string)($_GET['type'] ?? 'success');
  if (!in_array($msg_type, ['success', 'danger', 'warning', 'info'], true)) {
    $msg_type = 'success';
  }
}

$patients = [];
$q = "
  SELECT
    p.id,
    p.firstname,
    p.lastname,
    p.email,
    p.phone_number,
    p.gender,
    p.address,
    p.created_at,
    COALESCE(vb_stats.visited_count, 0) AS visited_count
  FROM patient p
  LEFT JOIN (
    SELECT patient_id, COUNT(*) AS visited_count
    FROM visit_booking
    WHERE status = 'visited'
    GROUP BY patient_id
  ) vb_stats ON vb_stats.patient_id = p.id
  ORDER BY p.created_at DESC, p.id DESC
  LIMIT 500
";
$res = $conn->query($q);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $patients[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patients | Admin</title>

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
          <li class="nav-item"><a class="nav-link active" href="patients.php">Patients</a></li>
          <li class="nav-item"><a class="nav-link" href="verify_doctors.php">Verify Doctors</a></li>
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
      <div>
        <h3 class="fw-bold mb-0">Patients</h3>
        <div class="text-muted small">View patient profiles and visited totals.</div>
      </div>
      <span class="badge bg-primary"><?php echo count($patients); ?></span>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Patient</th>
              <th>Contact</th>
              <th>Address</th>
              <th class="text-end">Visited</th>
              <th style="min-width: 110px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($patients)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No patients found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($patients as $p): ?>
                <?php
                $patient_id = (int)($p['id'] ?? 0);
                $name = trim((string)($p['firstname'] ?? '') . ' ' . (string)($p['lastname'] ?? ''));
                $email = (string)($p['email'] ?? '');
                $phone = (string)($p['phone_number'] ?? '');
                $gender = (string)($p['gender'] ?? '');
                $addr = (string)($p['address'] ?? '');
                ?>
                <tr>
                  <td>
                    <div class="fw-bold"><?php echo admin_h($name !== '' ? $name : '—'); ?></div>
                    <div class="text-muted small"><?php echo admin_h($gender !== '' ? $gender : '—'); ?></div>
                  </td>
                  <td>
                    <div class="small"><i class="fas fa-envelope me-1"></i><?php echo admin_h($email !== '' ? $email : '—'); ?></div>
                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo admin_h($phone !== '' ? $phone : '—'); ?></div>
                  </td>
                  <td class="small text-muted"><?php echo admin_h($addr !== '' ? $addr : '—'); ?></td>
                  <td class="text-end fw-bold"><?php echo (int)($p['visited_count'] ?? 0); ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="patient_edit.php?id=<?php echo $patient_id; ?>">
                      <i class="fas fa-pen-to-square me-1"></i>Edit
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>