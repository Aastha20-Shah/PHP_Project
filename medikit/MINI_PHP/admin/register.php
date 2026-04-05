<?php
require_once __DIR__ . '/_bootstrap.php';

if (admin_is_logged_in()) {
  header('Location: dashboard.php');
  exit;
}

// Only allow creating the first admin user.
$cnt = 0;
$cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM admin_users");
if ($cntRes) {
  $row = $cntRes->fetch_assoc();
  $cnt = (int)($row['cnt'] ?? 0);
}
if ($cnt > 0) {
  header('Location: login.php');
  exit;
}

$msg = '';
$msg_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $cpassword = (string)($_POST['cpassword'] ?? '');

  if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Please enter a valid name and email.';
  } elseif ($password === '' || strlen($password) < 8) {
    $msg = 'Password must be at least 8 characters.';
  } elseif ($password !== $cpassword) {
    $msg = 'Passwords do not match.';
  } else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO admin_users (name, email, password) VALUES (?,?,?)');
    if ($stmt) {
      $stmt->bind_param('sss', $name, $email, $hash);
      if ($stmt->execute()) {
        $stmt->close();
        $msg = 'Admin account created. You can now login.';
        $msg_type = 'success';
      } else {
        $stmt->close();
        $msg = 'Failed to create admin. Email may already exist.';
      }
    } else {
      $msg = 'Database error.';
    }
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Admin | Medkit</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../custom_style.css?v=<?php echo $css_v; ?>">
</head>

<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex align-items-center mb-3">
              <i class="fas fa-shield-halved text-primary me-2"></i>
              <h4 class="mb-0 fw-bold">Create First Admin</h4>
            </div>
            <p class="text-muted">This page is available only once to create the first admin account.</p>

            <?php if ($msg !== ''): ?>
              <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert">
                <?php echo admin_h($msg); ?>
                <?php if ($msg_type === 'success'): ?>
                  <div class="mt-2"><a class="alert-link" href="login.php">Go to Login</a></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

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
                  <label class="form-label fw-bold">Confirm Password</label>
                  <input type="password" name="cpassword" class="form-control" required>
                </div>
              </div>

              <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary">Create Admin</button>
              </div>

              <div class="text-center mt-4">
                <a href="../index.php" class="text-decoration-none text-muted small">
                  <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>