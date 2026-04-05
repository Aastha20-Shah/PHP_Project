<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$doctor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doctor_id <= 0) {
  http_response_code(400);
  echo 'Invalid doctor.';
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

function medikit_fetch_doctor_for_admin(mysqli $conn, int $doctor_id): ?array
{
  $q = "
    SELECT
      u.*,
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
    WHERE u.id = ? AND u.role_id = 2
    LIMIT 1
  ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }
  $stmt->bind_param('i', $doctor_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

$doctor = medikit_fetch_doctor_for_admin($conn, $doctor_id);
if (!$doctor) {
  http_response_code(404);
  echo 'Doctor not found.';
  exit;
}

$categories = [];
$resCat = $conn->query("SELECT id, category_name FROM category ORDER BY category_name ASC");
if ($resCat) {
  while ($row = $resCat->fetch_assoc()) {
    $categories[] = $row;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstname = trim((string)($_POST['firstname'] ?? ''));
  $lastname = trim((string)($_POST['lastname'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $phone_raw = trim((string)($_POST['phone_number'] ?? ''));
  $dob = trim((string)($_POST['date_of_birth'] ?? ''));
  $gender = trim((string)($_POST['gender'] ?? ''));
  $address = trim((string)($_POST['address'] ?? ''));
  $clinic_name = trim((string)($_POST['clinic_name'] ?? ''));
  $experience_years = (int)($_POST['experience_years'] ?? 0);
  $education = trim((string)($_POST['education'] ?? ''));
  $bio = trim((string)($_POST['bio'] ?? ''));
  $category_id = (int)($_POST['category_id'] ?? 0);
  $license_number = trim((string)($_POST['license_number'] ?? ''));

  $verification_status = (string)($_POST['verification_status'] ?? 'pending');
  $verification_reason = trim((string)($_POST['verification_reason'] ?? ''));

  if ($firstname === '' || $lastname === '') {
    $msg = 'Please enter doctor firstname and lastname.';
    $msg_type = 'danger';
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = 'Please enter a valid email (or leave blank).';
    $msg_type = 'danger';
  } elseif ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    $msg = 'Date of birth must be in YYYY-MM-DD format.';
    $msg_type = 'danger';
  } elseif (!in_array($verification_status, ['pending', 'verified', 'rejected'], true)) {
    $msg = 'Invalid verification status.';
    $msg_type = 'danger';
  } else {
    $phone_digits = preg_replace('/\D+/', '', $phone_raw);
    if ($phone_digits === '') {
      $phone_digits = '0';
    }
    if (strlen($phone_digits) > 20) {
      $phone_digits = substr($phone_digits, 0, 20);
    }

    if ($experience_years < 0) {
      $experience_years = 0;
    }

    $commission_percent = medikit_fixed_commission_percent();

    $stmt = $conn->prepare(
      "UPDATE users
        SET firstname = ?,
            lastname = ?,
            email = ?,
            phone_number = ?,
            date_of_birth = COALESCE(NULLIF(?, ''), date_of_birth),
            gender = ?,
            address = ?,
            clinic_name = ?,
            experience_years = ?,
            education = ?,
            bio = ?,
            category_id = NULLIF(?, 0),
            license_number = ?,
            commission_percent = ?
        WHERE id = ? AND role_id = 2"
    );

    if ($stmt) {
      $stmt->bind_param(
        'ssssssssissisdi',
        $firstname,
        $lastname,
        $email,
        $phone_digits,
        $dob,
        $gender,
        $address,
        $clinic_name,
        $experience_years,
        $education,
        $bio,
        $category_id,
        $license_number,
        $commission_percent,
        $doctor_id
      );
      $stmt->execute();
      $stmt->close();

      $status_updated = false;
      $status_msg = '';

      $license_doc = (string)($doctor['license_document'] ?? '');
      $has_doc = $license_doc !== '';

      if ($verification_status === 'verified') {
        if (!$has_doc || $license_number === '') {
          $status_msg = 'Cannot verify: license number and document are required.';
        } else {
          $admin_id = (int)$_SESSION['admin_id'];
          $stmt2 = $conn->prepare(
            "UPDATE users
              SET verification_status = 'verified',
                  verified_at = NOW(),
                  verified_by_admin_id = ?,
                  verification_reason = NULL
              WHERE id = ? AND role_id = 2"
          );
          if ($stmt2) {
            $stmt2->bind_param('ii', $admin_id, $doctor_id);
            $stmt2->execute();
            $stmt2->close();
            $status_updated = true;
          }
        }
      } elseif ($verification_status === 'rejected') {
        if ($verification_reason === '') {
          $verification_reason = 'Rejected by admin.';
        }
        if (strlen($verification_reason) > 255) {
          $verification_reason = substr($verification_reason, 0, 255);
        }

        $admin_id = (int)$_SESSION['admin_id'];
        $stmt2 = $conn->prepare(
          "UPDATE users
            SET verification_status = 'rejected',
                verified_at = NOW(),
                verified_by_admin_id = ?,
                verification_reason = ?
            WHERE id = ? AND role_id = 2"
        );
        if ($stmt2) {
          $stmt2->bind_param('isi', $admin_id, $verification_reason, $doctor_id);
          $stmt2->execute();
          $stmt2->close();
          $status_updated = true;
        }
      } else {
        $stmt2 = $conn->prepare(
          "UPDATE users
            SET verification_status = 'pending',
                verified_at = NULL,
                verified_by_admin_id = NULL,
                verification_reason = NULL
            WHERE id = ? AND role_id = 2"
        );
        if ($stmt2) {
          $stmt2->bind_param('i', $doctor_id);
          $stmt2->execute();
          $stmt2->close();
          $status_updated = true;
        }
      }

      $msg = 'Doctor updated successfully.';
      $msg_type = 'success';
      if ($status_msg !== '') {
        $msg = $msg . ' ' . $status_msg;
        $msg_type = 'warning';
      } elseif (!$status_updated) {
        $msg = $msg . ' Verification status could not be updated.';
        $msg_type = 'warning';
      }

      header('Location: doctor_edit.php?id=' . $doctor_id . '&msg=' . urlencode($msg) . '&type=' . urlencode($msg_type));
      exit;
    }

    $msg = 'Database error.';
    $msg_type = 'danger';
  }
}

$doctor = medikit_fetch_doctor_for_admin($conn, $doctor_id);
if (!$doctor) {
  http_response_code(404);
  echo 'Doctor not found.';
  exit;
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Doctor | Admin</title>

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
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
      <div>
        <a href="doctors.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Back to Doctors</a>
        <h3 class="fw-bold mb-0 mt-1">Edit Doctor</h3>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary">Visited: <?php echo (int)($doctor['visited_count'] ?? 0); ?></span>
        <span class="badge bg-secondary">ID: <?php echo (int)$doctor_id; ?></span>
      </div>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 p-lg-5">
        <div class="row g-4">
          <div class="col-lg-8">
            <form method="POST" action="">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">First Name</label>
                  <input type="text" name="firstname" class="form-control" value="<?php echo admin_h((string)($doctor['firstname'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Last Name</label>
                  <input type="text" name="lastname" class="form-control" value="<?php echo admin_h((string)($doctor['lastname'] ?? '')); ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">Email</label>
                  <input type="email" name="email" class="form-control" value="<?php echo admin_h((string)($doctor['email'] ?? '')); ?>" placeholder="optional">
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">Phone</label>
                  <input type="text" name="phone_number" class="form-control" value="<?php echo admin_h((string)($doctor['phone_number'] ?? '')); ?>">
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Date of Birth</label>
                  <input type="date" name="date_of_birth" class="form-control" value="<?php echo admin_h((string)($doctor['date_of_birth'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-bold">Gender</label>
                  <input type="text" name="gender" class="form-control" value="<?php echo admin_h((string)($doctor['gender'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-bold">Experience (Years)</label>
                  <input type="number" name="experience_years" class="form-control" min="0" step="1" value="<?php echo (int)($doctor['experience_years'] ?? 0); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-bold">Address</label>
                  <input type="text" name="address" class="form-control" value="<?php echo admin_h((string)($doctor['address'] ?? '')); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-bold">Clinic Name</label>
                  <input type="text" name="clinic_name" class="form-control" value="<?php echo admin_h((string)($doctor['clinic_name'] ?? '')); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">Category</label>
                  <select name="category_id" class="form-select">
                    <option value="0">— Select Category —</option>
                    <?php foreach ($categories as $c): ?>
                      <?php
                      $cid = (int)($c['id'] ?? 0);
                      $selected = ($cid > 0 && (int)($doctor['category_id'] ?? 0) === $cid) ? 'selected' : '';
                      ?>
                      <option value="<?php echo $cid; ?>" <?php echo $selected; ?>><?php echo admin_h((string)($c['category_name'] ?? '')); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">Commission % (Fixed)</label>
                  <input type="text" class="form-control" value="<?php echo number_format(medikit_fixed_commission_percent(), 2); ?>%" readonly>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">License Number</label>
                  <input type="text" name="license_number" class="form-control" value="<?php echo admin_h((string)($doctor['license_number'] ?? '')); ?>">
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">License Document</label>
                  <div class="form-control bg-light">
                    <?php if ((string)($doctor['license_document'] ?? '') !== ''): ?>
                      <a href="license.php?doctor_id=<?php echo (int)$doctor_id; ?>" target="_blank" class="text-primary text-decoration-none">
                        <i class="fas fa-file-lines me-1"></i>View Document
                      </a>
                    <?php else: ?>
                      <span class="text-danger">No document uploaded</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label fw-bold">Education</label>
                  <input type="text" name="education" class="form-control" value="<?php echo admin_h((string)($doctor['education'] ?? '')); ?>">
                </div>

                <div class="col-12">
                  <label class="form-label fw-bold">Bio</label>
                  <textarea name="bio" class="form-control" rows="3"><?php echo admin_h((string)($doctor['bio'] ?? '')); ?></textarea>
                </div>

                <hr class="my-3">

                <div class="col-md-4">
                  <label class="form-label fw-bold">Verification Status</label>
                  <select name="verification_status" class="form-select">
                    <?php
                    $vs = (string)($doctor['verification_status'] ?? 'pending');
                    ?>
                    <option value="pending" <?php echo ($vs === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="verified" <?php echo ($vs === 'verified') ? 'selected' : ''; ?>>Verified</option>
                    <option value="rejected" <?php echo ($vs === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                  </select>
                  <div class="form-text">To verify, license number + document are required.</div>
                </div>

                <div class="col-md-8">
                  <label class="form-label fw-bold">Verification Reason (for Rejected)</label>
                  <input type="text" name="verification_reason" class="form-control" value="<?php echo admin_h((string)($doctor['verification_reason'] ?? '')); ?>" placeholder="optional">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                  <a href="doctors.php" class="btn btn-outline-secondary">Cancel</a>
                  <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
              </div>
            </form>
          </div>

          <div class="col-lg-4">
            <div class="card border-0 bg-light h-100">
              <div class="card-body">
                <h6 class="fw-bold">Summary</h6>
                <div class="small text-muted mb-2">Specialities</div>
                <div class="small fw-semibold mb-3"><?php echo admin_h((string)($doctor['specialities'] ?? '') !== '' ? (string)$doctor['specialities'] : '—'); ?></div>

                <div class="small text-muted mb-2">Created</div>
                <div class="small fw-semibold mb-3"><?php echo admin_h((string)($doctor['created_at'] ?? '')); ?></div>

                <div class="small text-muted mb-2">Updated</div>
                <div class="small fw-semibold mb-0"><?php echo admin_h((string)($doctor['updated_at'] ?? '')); ?></div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>