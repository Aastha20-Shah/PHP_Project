<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $cpassword = (string)($_POST['cpassword'] ?? '');

  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Please enter a valid name and email.';
    $msg_type = 'danger';
  } elseif ($password === '' || strlen($password) < 8) {
    $msg = 'Password must be at least 8 characters.';
    $msg_type = 'danger';
  } elseif ($password !== $cpassword) {
    $msg = 'Passwords do not match.';
    $msg_type = 'danger';
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admin_users (name, email, password) VALUES (?,?,?)');
    if ($stmt) {
      $stmt->bind_param('sss', $name, $email, $hash);
      if ($stmt->execute()) {
        $msg = 'Admin account created successfully.';
        $msg_type = 'success';
      } else {
        $msg = 'Failed to create admin. Email may already exist.';
        $msg_type = 'danger';
      }
      $stmt->close();
    } else {
      $msg = 'Database error.';
      $msg_type = 'danger';
    }
  }
}

$admins = [];
$res = $conn->query('SELECT id, name, email, created_at FROM admin_users ORDER BY created_at DESC, id DESC LIMIT 200');
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $admins[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admins | Admin</title>

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
          <li class="nav-item"><a class="nav-link" href="verify_doctors.php">Verify Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="commissions.php">Commissions</a></li>
          <li class="nav-item"><a class="nav-link active" href="admin_users.php">Admins</a></li>
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
        <h3 class="fw-bold mb-0">Admins</h3>
        <div class="text-muted small">Create and manage admin accounts.</div>
      </div>
      <span class="badge bg-primary"><?php echo count($admins); ?></span>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Create Admin</h5>
            <form method="POST" action="">
              <div class="mb-3">
                <label class="form-label fw-bold">Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Password</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Confirm</label>
                  <input type="password" name="cpassword" class="form-control" required>
                </div>
              </div>
              <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary">Create Admin</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 py-3">
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="fw-bold mb-0">Admin Accounts</h5>
              <span class="badge bg-primary"><?php echo count($admins); ?></span>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($admins)): ?>
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">No admins found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($admins as $a): ?>
                    <tr>
                      <td class="fw-semibold"><?php echo admin_h((string)($a['name'] ?? '')); ?></td>
                      <td class="small"><?php echo admin_h((string)($a['email'] ?? '')); ?></td>
                      <td class="small text-muted"><?php echo admin_h((string)($a['created_at'] ?? '')); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>