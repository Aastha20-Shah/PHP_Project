<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($patient_id <= 0) {
  http_response_code(400);
  echo 'Invalid patient.';
  exit;
}

$msg = '';
$msg_type = 'success';
if (isset($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
  $msg_type = (string)($_GET['type'] ?? 'success');
  if (!in_array($msg_type, ['success', 'danger', 'warning', 'info'], true)) {
    $msg_type = 'success';
  }
}

function medikit_fetch_patient_for_admin(mysqli $conn, int $patient_id): ?array
{
  $q = "
    SELECT
      p.*,
      COALESCE(vb_stats.visited_count, 0) AS visited_count
    FROM patient p
    LEFT JOIN (
      SELECT patient_id, COUNT(*) AS visited_count
      FROM visit_booking
      WHERE status = 'visited'
      GROUP BY patient_id
    ) vb_stats ON vb_stats.patient_id = p.id
    WHERE p.id = ?
    LIMIT 1
  ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('i', $patient_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

$patient = medikit_fetch_patient_for_admin($conn, $patient_id);
if (!$patient) {
  http_response_code(404);
  echo 'Patient not found.';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstname = trim((string)($_POST['firstname'] ?? ''));
  $lastname = trim((string)($_POST['lastname'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone_raw = trim((string)($_POST['phone_number'] ?? ''));
  $dob = trim((string)($_POST['date_of_birth'] ?? ''));
  $gender = trim((string)($_POST['gender'] ?? ''));
  $address = trim((string)($_POST['address'] ?? ''));

  if ($firstname === '' || $lastname === '') {
    $msg = 'Please enter patient firstname and lastname.';
    $msg_type = 'danger';
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Please enter a valid email (or leave blank).';
    $msg_type = 'danger';
  } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    $msg = 'Date of birth must be in YYYY-MM-DD format.';
    $msg_type = 'danger';
  } else {
    $phone_digits = preg_replace('/\D+/', '', $phone_raw);
    if ($phone_digits === '') {
      $phone_digits = '0';
    }
    if (strlen($phone_digits) > 20) {
      $phone_digits = substr($phone_digits, 0, 20);
    }

    $stmt = $conn->prepare(
      "UPDATE patient
        SET firstname = ?,
            lastname = ?,
            email = NULLIF(?, ''),
            phone_number = ?,
            date_of_birth = COALESCE(NULLIF(?, ''), date_of_birth),
            gender = ?,
            address = ?
        WHERE id = ?"
    );

    if ($stmt) {
      $stmt->bind_param(
        'sssssssi',
        $firstname,
        $lastname,
        $email,
        $phone_digits,
        $dob,
        $gender,
        $address,
        $patient_id
      );
      $stmt->execute();
      $stmt->close();

      $msg = 'Patient updated successfully.';
      $msg_type = 'success';
      header('Location: patient_edit.php?id=' . $patient_id . '&msg=' . urlencode($msg) . '&type=' . urlencode($msg_type));
      exit;
    }

    $msg = 'Database error.';
    $msg_type = 'danger';
  }
}

$patient = medikit_fetch_patient_for_admin($conn, $patient_id);
if (!$patient) {
  http_response_code(404);
  echo 'Patient not found.';
  exit;
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Patient | Admin</title>

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
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
      <div>
        <a href="patients.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Back to Patients</a>
        <h3 class="fw-bold mb-0 mt-1">Edit Patient</h3>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary">Visited: <?php echo (int)($patient['visited_count'] ?? 0); ?></span>
        <span class="badge bg-secondary">ID: <?php echo (int)$patient_id; ?></span>
      </div>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <form method="POST" action="">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">First Name</label>
              <input type="text" name="firstname" class="form-control" value="<?php echo admin_h((string)($patient['firstname'] ?? '')); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Last Name</label>
              <input type="text" name="lastname" class="form-control" value="<?php echo admin_h((string)($patient['lastname'] ?? '')); ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-bold">Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo admin_h((string)($patient['email'] ?? '')); ?>" placeholder="optional">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Phone</label>
              <input type="text" name="phone_number" class="form-control" value="<?php echo admin_h((string)($patient['phone_number'] ?? '')); ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-bold">Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control" value="<?php echo admin_h((string)($patient['date_of_birth'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Gender</label>
              <input type="text" name="gender" class="form-control" value="<?php echo admin_h((string)($patient['gender'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Address</label>
              <input type="text" name="address" class="form-control" value="<?php echo admin_h((string)($patient['address'] ?? '')); ?>">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2">
              <a href="patients.php" class="btn btn-outline-secondary">Cancel</a>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>