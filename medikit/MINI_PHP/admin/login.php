<?php
require_once __DIR__ . '/_bootstrap.php';

if (admin_is_logged_in()) {
  header('Location: dashboard.php');
  exit;
}

$msg = '';

$has_admin = false;
$cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM admin_users");
if ($cntRes) {
  $row = $cntRes->fetch_assoc();
  $has_admin = ((int)($row['cnt'] ?? 0)) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $msg = 'Please enter a valid email and password.';
  } else {
    $stmt = $conn->prepare('SELECT id, name, password FROM admin_users WHERE email = ? LIMIT 1');
    if ($stmt) {
      $stmt->bind_param('s', $email);
      $stmt->execute();
      $admin = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      if ($admin && password_verify($password, (string)$admin['password'])) {
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = (string)$admin['name'];
        header('Location: dashboard.php');
        exit;
      }
    }

    $msg = 'Invalid email or password.';
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Medkit</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../custom_style.css?v=<?php echo $css_v; ?>">

  <style>
    .auth-image-half {
      background: linear-gradient(rgba(26, 118, 209, 0.9), rgba(26, 118, 209, 0.9)),
        url('https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=2070&auto=format&fit=crop') no-repeat center center;
      background-size: cover;
      color: #fff;
    }
  </style>
</head>

<body>
  <div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">

      <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background-color: var(--secondary-color);">
        <div class="p-4 p-md-5 w-100" style="max-width: 550px;">

          <div class="text-center d-lg-none mb-4">
            <a class="navbar-brand text-primary fs-2" href="../index.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
          </div>

          <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
              <h2 class="fw-bold mb-2 text-center">Admin Login</h2>
              <p class="text-muted text-center mb-4">Login to verify doctors and manage commissions.</p>

              <?php if ($msg !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo admin_h($msg); ?></div>
              <?php endif; ?>

              <form method="POST" action="">
                <div class="mb-3">
                  <label class="form-label fw-bold">Email</label>
                  <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold">Password</label>
                  <input type="password" name="password" class="form-control" required>
                </div>
                <div class="d-grid mt-4">
                  <button type="submit" class="btn btn-primary">Login</button>
                </div>
              </form>

              <?php if (!$has_admin): ?>
                <div class="alert alert-warning mt-4 mb-0">
                  No admin account found. <a class="alert-link" href="register.php">Create the first admin</a>.
                </div>
              <?php endif; ?>

              <div class="text-center mt-4">
                <a href="../index.php" class="text-decoration-none text-muted small">
                  <i class="fas fa-arrow-left me-1"></i> Back to Home
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center auth-image-half">
        <div class="text-center p-5">
          <a class="navbar-brand text-white fs-1 mb-4 d-block" href="../index.php">
            <i class="fas fa-shield-halved me-2"></i>Medkit
          </a>
          <h1 class="display-4 fw-bold mb-3">Admin Panel</h1>
          <p class="lead">Verify doctors and track earnings.</p>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>