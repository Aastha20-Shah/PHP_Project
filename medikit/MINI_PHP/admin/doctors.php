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

$doctors = [];
$q = "
  SELECT
    u.id,
    u.firstname,
    u.lastname,
    u.email,
    u.phone_number,
    u.clinic_name,
    u.address,
    u.license_number,
    u.license_document,
    u.verification_status,
    u.created_at,
    c.category_name,
    COALESCE(vb_stats.visited_count, 0) AS visited_count,
    COALESCE(spec.specialities, '') AS specialities
  FROM users u
  LEFT JOIN category c ON c.id = u.category_id
  LEFT JOIN (
    SELECT doctor_id, COUNT(*) AS visited_count
    FROM visit_booking
    WHERE status = 'visited'
    GROUP BY doctor_id
  ) vb_stats ON vb_stats.doctor_id = u.id
  LEFT JOIN (
    SELECT ds.doctor_id,
           GROUP_CONCAT(s.doctor_speciality ORDER BY s.doctor_speciality SEPARATOR ', ') AS specialities
    FROM doctor_speciality ds
    INNER JOIN speciality s ON s.id = ds.speciality_id
    GROUP BY ds.doctor_id
  ) spec ON spec.doctor_id = u.id
  WHERE u.role_id = 2
  ORDER BY u.created_at DESC, u.id DESC
  LIMIT 500
";
$res = $conn->query($q);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $doctors[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctors | Admin</title>

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
          <li class="nav-item"><a class="nav-link active" href="doctors.php">Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="patients.php">Patients</a></li>
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
        <h3 class="fw-bold mb-0">Doctors</h3>
        <div class="text-muted small">Manage doctor profiles, verification, and visited totals.</div>
      </div>
      <span class="badge bg-primary"><?php echo count($doctors); ?></span>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Doctor</th>
              <th>Contact</th>
              <th>Speciality</th>
              <th>Status</th>
              <th>License</th>
              <th class="text-end">Commission %</th>
              <th class="text-end">Visited</th>
              <th style="min-width: 110px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($doctors)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No doctors found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($doctors as $d): ?>
                <?php
                $doctor_id = (int)($d['id'] ?? 0);
                $name = trim((string)($d['firstname'] ?? '') . ' ' . (string)($d['lastname'] ?? ''));
                $clinic = (string)($d['clinic_name'] ?? '');
                $email = (string)($d['email'] ?? '');
                $phone = (string)($d['phone_number'] ?? '');
                $category = (string)($d['category_name'] ?? '');
                $specialities = (string)($d['specialities'] ?? '');
                $status = (string)($d['verification_status'] ?? 'pending');

                $badge = 'secondary';
                if ($status === 'verified') $badge = 'success';
                elseif ($status === 'pending') $badge = 'warning';
                elseif ($status === 'rejected') $badge = 'danger';

                $license_no = (string)($d['license_number'] ?? '');
                $has_doc = (string)($d['license_document'] ?? '') !== '';
                ?>
                <tr>
                  <td>
                    <div class="fw-bold">Dr. <?php echo admin_h($name !== '' ? $name : '—'); ?></div>
                    <div class="text-muted small"><?php echo admin_h($clinic); ?></div>
                  </td>
                  <td>
                    <div class="small"><i class="fas fa-envelope me-1"></i><?php echo admin_h($email !== '' ? $email : '—'); ?></div>
                    <div class="small text-muted"><i class="fas fa-phone me-1"></i><?php echo admin_h($phone !== '' ? $phone : '—'); ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold small"><?php echo admin_h($category !== '' ? $category : '—'); ?></div>
                    <div class="text-muted small"><?php echo admin_h($specialities !== '' ? $specialities : '—'); ?></div>
                  </td>
                  <td>
                    <span class="badge bg-<?php echo $badge; ?> text-uppercase"><?php echo admin_h($status); ?></span>
                  </td>
                  <td>
                    <div class="small"><strong>No:</strong> <?php echo admin_h($license_no !== '' ? $license_no : '—'); ?></div>
                    <div class="small">
                      <?php if ($has_doc): ?>
                        <a href="license.php?doctor_id=<?php echo $doctor_id; ?>" target="_blank" class="text-primary text-decoration-none">
                          <i class="fas fa-file-lines me-1"></i>View
                        </a>
                      <?php else: ?>
                        <span class="text-danger small">No document</span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="text-end"><?php echo number_format(medikit_fixed_commission_percent(), 2); ?>%</td>
                  <td class="text-end fw-bold"><?php echo (int)($d['visited_count'] ?? 0); ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="doctor_edit.php?id=<?php echo $doctor_id; ?>">
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