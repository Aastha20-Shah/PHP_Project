<?php
session_start();
include("config.php");
include("profile_image_helpers.php");
include("doctor_notification_helpers.php");
include("admin_helpers.php");

medikit_ensure_profile_image_schema($conn);
medikit_doctor_verification_ensure_schema($conn);

if (!isset($_SESSION['doctor_id'])) {
  header("Location: login.php");
  exit;
}

$doctor_id = (int)$_SESSION['doctor_id'];

$notifications = medikit_doctor_unseen_notifications_list($conn, $doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, $doctor_id);

$flash = '';
$flash_type = 'success';

function medikit_trim_limit(string $value, int $maxLen): string
{
  $value = trim($value);
  if ($maxLen > 0 && strlen($value) > $maxLen) {
    $value = substr($value, 0, $maxLen);
  }
  return $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'update_profile') {
    $phone = medikit_trim_limit((string)($_POST['phone_number'] ?? ''), 50);
    $address = medikit_trim_limit((string)($_POST['address'] ?? ''), 255);
    $clinic_name = medikit_trim_limit((string)($_POST['clinic_name'] ?? ''), 150);
    $education = medikit_trim_limit((string)($_POST['education'] ?? ''), 255);
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $bio = medikit_trim_limit((string)($_POST['bio'] ?? ''), 2000);

    if ($experience_years < 0) {
      $experience_years = 0;
    }
    if ($experience_years > 80) {
      $experience_years = 80;
    }

    $stmt = $conn->prepare('UPDATE users SET phone_number = ?, address = ?, clinic_name = ?, education = ?, experience_years = ?, bio = ? WHERE id = ? AND role_id = 2');
    if ($stmt) {
      $stmt->bind_param('ssssisi', $phone, $address, $clinic_name, $education, $experience_years, $bio, $doctor_id);
      if ($stmt->execute()) {
        $flash = 'Profile updated successfully.';
        $flash_type = 'success';
      } else {
        $flash = 'Failed to update profile.';
        $flash_type = 'danger';
      }
      $stmt->close();
    } else {
      $flash = 'Server error. Please try again.';
      $flash_type = 'danger';
    }
  } elseif ($action === 'change_password') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($new === '' || strlen($new) < 6) {
      $flash = 'New password must be at least 6 characters.';
      $flash_type = 'danger';
    } elseif ($new !== $confirm) {
      $flash = 'New password and confirm password do not match.';
      $flash_type = 'danger';
    } else {
      $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? AND role_id = 2 LIMIT 1');
      if ($stmt) {
        $stmt->bind_param('i', $doctor_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $hash = (string)($row['password'] ?? '');

        if ($hash === '' || !password_verify($current, $hash)) {
          $flash = 'Current password is incorrect.';
          $flash_type = 'danger';
        } else {
          $new_hash = password_hash($new, PASSWORD_DEFAULT);
          $stmtUp = $conn->prepare('UPDATE users SET password = ? WHERE id = ? AND role_id = 2');
          if ($stmtUp) {
            $stmtUp->bind_param('si', $new_hash, $doctor_id);
            if ($stmtUp->execute()) {
              $flash = 'Password changed successfully.';
              $flash_type = 'success';
            } else {
              $flash = 'Failed to change password.';
              $flash_type = 'danger';
            }
            $stmtUp->close();
          } else {
            $flash = 'Server error. Please try again.';
            $flash_type = 'danger';
          }
        }
      } else {
        $flash = 'Server error. Please try again.';
        $flash_type = 'danger';
      }
    }
  }
}

// Fetch doctor profile
$doctor = null;
$q = "
    SELECT
      u.*,
      c.category_name,
      GROUP_CONCAT(DISTINCT s.doctor_speciality ORDER BY s.doctor_speciality SEPARATOR ', ') AS specialities
    FROM users u
    LEFT JOIN category c ON u.category_id = c.id
    LEFT JOIN doctor_speciality ds ON ds.doctor_id = u.id
    LEFT JOIN speciality s ON s.id = ds.speciality_id
    WHERE u.id = ? AND u.role_id = 2
    GROUP BY u.id
    LIMIT 1
";

$stmt = $conn->prepare($q);
if ($stmt) {
  $stmt->bind_param('i', $doctor_id);
  $stmt->execute();
  $doctor = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

if (!$doctor) {
  header('Location: logout.php');
  exit;
}

$doctor['profile_image'] = $doctor['profile_image'] ?? '';

$profile_image = (string)($doctor['profile_image'] ?? '');
$profile_image = str_replace('\\', '/', $profile_image);
$profile_image = ltrim($profile_image, '/');
$has_photo = ($profile_image !== ''
  && strpos($profile_image, 'uploads/doctors/') === 0
  && file_exists(__DIR__ . '/' . $profile_image));

$doctor_avatar_src = $has_photo
  ? $profile_image
  : 'https://ui-avatars.com/api/?name=' . urlencode((string)($doctor['firstname'] ?? 'Doctor') . '+' . (string)($doctor['lastname'] ?? '')) . '&background=5a8dee&color=fff';

$verification = (string)($doctor['verification_status'] ?? 'verified');
$license_number = trim((string)($doctor['license_number'] ?? ''));
$license_doc = trim((string)($doctor['license_document'] ?? ''));

$badge_class = 'badge-soft badge-verified';
$badge_text = 'Verified';
if ($verification === 'pending') {
  $badge_class = 'badge-soft badge-pending';
  $badge_text = 'Pending';
} elseif ($verification === 'rejected') {
  $badge_class = 'badge-soft badge-rejected';
  $badge_text = 'Rejected';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - Doctor Panel</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f3f7;
      color: #5a5a5a;
    }

    /* TOP HEADER */
    .top-header {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 60px;
      background: #ffffff;
      box-shadow: -3 2px 10px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 30px;
      z-index: 1001;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 7px;
      margin-left: 25px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 22px;
      font-weight: 700;
      color: #333;
    }

    .logo i {
      color: #ff6b6b;
      font-size: 28px;
    }

    .menu-toggle {
      width: 40px;
      height: 40px;
      border: none;
      background: none;
      cursor: pointer;
      color: #666;
      font-size: 20px;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 25px;
    }

    .header-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: #f8f9fa;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #666;
      cursor: pointer;
      position: relative;
    }

    .header-icon:hover {
      background: #e9ecef;
    }

    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #ff6b6b;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 11px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
    }

    .user-name {
      font-weight: 600;
      color: #333;
      font-size: 14px;
    }

    .flag-icon {
      width: 24px;
      height: 18px;
      border-radius: 3px;
    }

    /* SIDEBAR */
    .sidebar {
      width: 280px;
      position: fixed;
      left: 0;
      top: 60px;
      bottom: 0;
      background: #ffffff;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
      overflow-y: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      z-index: 1000;
    }

    .sidebar::-webkit-scrollbar {
      width: 0;
      height: 0;
    }

    .doctor-profile-sidebar {
      padding: 25px 20px 20px;
      text-align: center;
      border-bottom: 1px solid #f0f0f0;
      margin-top: -5px;
    }

    .profile-image-wrapper {
      width: 115px;
      height: 115px;
      margin: 0 auto 18px;
      position: relative;
    }

    .profile-image-container {
      width: 115px;
      height: 115px;
      border-radius: 20px;
      overflow: hidden;
      position: relative;
      border: 4px solid #ffffff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .profile-image {
      width: 100%;
      height: 100%;
      border-radius: 16px;
      object-fit: cover;
      display: block;
    }

    .profile-placeholder {
      width: 100%;
      height: 100%;
      border-radius: 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 46px;
      font-weight: 700;
    }

    .profile-name {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
    }

    .profile-role {
      font-size: 11px;
      color: #999;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 500;
    }

    .nav-menu {
      padding: 10px 0;
    }

    .nav-label {
      padding: 10px 20px 8px;
      font-size: 11px;
      font-weight: 600;
      color: #aaa;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 12px 20px;
      color: #666;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 14px;
      margin: 2px 10px;
      border-radius: 8px;
    }

    .nav-item i {
      width: 20px;
      font-size: 16px;
    }

    .nav-item:hover {
      background: #f8f9fa;
      color: #5a8dee;
    }

    .nav-item.active {
      background: #e8f0fe;
      color: #5a8dee;
      font-weight: 500;
    }

    /* MAIN CONTENT */
    .main-content {
      margin-left: 280px;
      margin-top: 70px;
      padding: 30px 32px 40px;
      background: #f4f7fb;
      min-height: calc(100vh - 70px);
      position: relative;
      overflow: hidden;
    }

    .main-content::before {
      content: "";
      position: absolute;
      width: 360px;
      height: 360px;
      right: -120px;
      top: -120px;
      background: radial-gradient(circle, rgba(90, 141, 238, 0.18), rgba(90, 141, 238, 0));
      pointer-events: none;
    }

    .main-content::after {
      content: "";
      position: absolute;
      width: 420px;
      height: 420px;
      left: -180px;
      bottom: -160px;
      background: radial-gradient(circle, rgba(78, 205, 196, 0.18), rgba(78, 205, 196, 0));
      pointer-events: none;
    }

    .breadcrumb-bar {
      background: #ffffff;
      border-radius: 16px;
      padding: 18px 22px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      margin-bottom: 22px;
      position: relative;
      z-index: 1;
    }

    .breadcrumb-title {
      font-size: 15px;
      font-weight: 700;
      color: #333;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .panel-card {
      background: #ffffff;
      border-radius: 18px;
      padding: 22px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
      position: relative;
      z-index: 1;
    }

    .field-label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #8b8b8b;
      margin-bottom: 6px;
    }

    .badge-soft {
      font-weight: 600;
      font-size: 12px;
      border-radius: 999px;
      padding: 6px 12px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .badge-verified {
      background: #ecfdf5;
      color: #059669;
    }

    .badge-pending {
      background: #fff7ed;
      color: #ea580c;
    }

    .badge-rejected {
      background: #fef2f2;
      color: #dc2626;
    }

    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
      }

      .sidebar {
        display: none;
      }
    }
  </style>
</head>

<body>
  <!-- TOP HEADER -->
  <div class="top-header">
    <div class="header-left">
      <div class="logo">
        <i class="fa-solid fa-stethoscope"></i>
        <span>Medkit</span>
      </div>
      <button class="menu-toggle" type="button" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <div class="header-right">
      <div class="dropdown" style="position: relative;">
        <button class="header-icon" type="button" data-notif-toggle aria-label="Notifications" aria-expanded="false">
          <i class="fas fa-bell"></i>
          <?php if (!empty($notification_count)): ?>
            <span class="notification-badge"><?= (int)$notification_count ?></span>
          <?php endif; ?>
        </button>

        <div class="dropdown-menu dropdown-menu-end p-0" data-notif-menu style="min-width: 320px; max-height: 380px; overflow: auto;">
          <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-semibold">New Appointments</span>
            <?php if (!empty($notification_count)): ?>
              <form method="POST" action="doctor_notifications.php" class="m-0">
                <input type="hidden" name="action" value="clear_all">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
                <input type="hidden" name="redirect_qs" value="<?= htmlspecialchars(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '') ?>">
                <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Clear all</button>
              </form>
            <?php endif; ?>
          </div>

          <?php if (empty($notifications)): ?>
            <div class="px-3 py-3 text-center text-muted small">No new appointments.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <?php
              $n_patient = trim((string)($n['patient_firstname'] ?? '') . ' ' . (string)($n['patient_lastname'] ?? ''));
              $n_date = '-';
              if (!empty($n['appointment_date'])) {
                $n_date = date('M j, Y', strtotime((string)$n['appointment_date']));
              }
              $n_time = '';
              if (!empty($n['start_time'])) {
                $n_time = date('g:i A', strtotime((string)$n['start_time']));
              }
              ?>
              <div class="px-3 py-2 border-bottom">
                <div class="fw-semibold small"><?= htmlspecialchars($n_patient !== '' ? $n_patient : 'New appointment') ?></div>
                <div class="text-muted small"><?= htmlspecialchars($n_date) ?><?= $n_time !== '' ? ' • ' . htmlspecialchars($n_time) : '' ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <img src="https://flagcdn.com/w40/us.png" alt="US" class="flag-icon">

      <a href="doctor_profile.php" class="user-profile text-decoration-none" title="View Profile" aria-label="View Profile">
        <span class="user-name"><?= htmlspecialchars((string)($doctor['firstname'] ?? '') . ' ' . (string)($doctor['lastname'] ?? '')) ?></span>
        <img src="<?= htmlspecialchars($doctor_avatar_src) ?>" alt="Profile" class="user-avatar">
      </a>
    </div>
  </div>

  <!-- LEFT SIDEBAR -->
  <div class="sidebar">
    <div class="doctor-profile-sidebar">
      <div class="profile-image-wrapper">
        <form id="profileImageForm" action="doctor_profile_image.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
          <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display: none;">
          <label for="profileImageInput" style="cursor: pointer; display: block; width: 115px; height: 115px; margin: 0 auto;">
            <div class="profile-image-container" id="sidebarProfileImage">
              <?php if ($has_photo): ?>
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="profile-image">
              <?php else: ?>
                <div class="profile-placeholder">
                  <?= strtoupper(substr((string)($doctor['firstname'] ?? 'D'), 0, 1)) ?>
                </div>
              <?php endif; ?>
            </div>
          </label>
        </form>
      </div>

      <?php if ($has_photo): ?>
        <form method="POST" action="doctor_profile_image.php" class="text-center mt-2" onsubmit="return confirm('Remove profile photo?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
        </form>
      <?php endif; ?>

      <div class="profile-name"><?= htmlspecialchars((string)($doctor['firstname'] ?? '') . ' ' . (string)($doctor['lastname'] ?? '')) ?></div>
      <div class="profile-role">DOCTOR</div>
    </div>

    <nav class="nav-menu">
      <div class="nav-label">MAIN</div>
      <a href="doctor_dashboard.php" class="nav-item">
        <i class="fas fa-th-large"></i>
        <span>Dashboard</span>
      </a>
      <a href="booking.php" class="nav-item">
        <i class="fas fa-calendar-check"></i>
        <span>Appointments</span>
      </a>
      <a href="add_day_time.php" class="nav-item">
        <i class="fas fa-clock"></i>
        <span>Day &amp; Time</span>
      </a>
      <a href="doctor_patients.php" class="nav-item">
        <i class="fas fa-users"></i>
        <span>Patients</span>
      </a>
      <a href="doctor_analytics.php" class="nav-item">
        <i class="fas fa-chart-line"></i>
        <span>Analytics</span>
      </a>
      <a href="doctor_billing.php" class="nav-item">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Billing</span>
      </a>
      <a href="doctor_prescriptions.php" class="nav-item">
        <i class="fas fa-file-prescription"></i>
        <span>Prescriptions</span>
      </a>
      <a href="logout.php" class="nav-item">
        <i class="fas fa-right-from-bracket"></i>
        <span>Logout</span>
      </a>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="breadcrumb-bar">
      <div class="breadcrumb-title">
        <span>My Profile</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-user"></i>
      </div>
      <div class="breadcrumb-links">
        <span style="color: #5a8dee; font-weight: 600;">Profile</span>
      </div>
    </div>

    <?php if ($flash !== ''): ?>
      <div class="alert alert-<?= htmlspecialchars($flash_type) ?>" role="alert" style="position: relative; z-index: 1;">
        <?= htmlspecialchars($flash) ?>
      </div>
    <?php endif; ?>

    <div class="row g-4" style="position: relative; z-index: 1;">
      <div class="col-lg-7">
        <div class="panel-card mb-4">
          <div class="d-flex align-items-center gap-3 mb-4">
            <img src="<?= htmlspecialchars($doctor_avatar_src) ?>" alt="Profile" style="width:64px;height:64px;border-radius:14px;object-fit:cover;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div>
              <div class="fw-bold" style="font-size:16px; color:#111827;">
                Dr. <?= htmlspecialchars((string)($doctor['firstname'] ?? '') . ' ' . (string)($doctor['lastname'] ?? '')) ?>
              </div>
              <div class="text-muted" style="font-size:13px;">
                <?= htmlspecialchars((string)($doctor['email'] ?? '')) ?>
              </div>
            </div>
            <div class="ms-auto">
              <span class="<?= htmlspecialchars($badge_class) ?>"><?= htmlspecialchars($badge_text) ?></span>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <div class="field-label">Gender</div>
              <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($doctor['gender'] ?? '-')) ?></div>
            </div>
            <div class="col-sm-6">
              <div class="field-label">Date of Birth</div>
              <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($doctor['date_of_birth'] ?? '-')) ?></div>
            </div>
            <div class="col-sm-6">
              <div class="field-label">Category</div>
              <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($doctor['category_name'] ?? '-')) ?></div>
            </div>
            <div class="col-sm-6">
              <div class="field-label">Specialities</div>
              <div class="fw-semibold text-dark"><?= htmlspecialchars((string)($doctor['specialities'] ?? '-')) ?></div>
            </div>
          </div>

          <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="update_profile">

            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label fw-semibold">Phone Number (Editable)</label>
                <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars((string)($doctor['phone_number'] ?? '')) ?>" maxlength="50">
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold">Clinic Name (Editable)</label>
                <input type="text" name="clinic_name" class="form-control" value="<?= htmlspecialchars((string)($doctor['clinic_name'] ?? '')) ?>" maxlength="150">
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold">Experience (Years)</label>
                <input type="number" name="experience_years" class="form-control" value="<?= htmlspecialchars((string)($doctor['experience_years'] ?? '0')) ?>" min="0" max="80">
              </div>
              <div class="col-sm-6">
                <label class="form-label fw-semibold">Education</label>
                <input type="text" name="education" class="form-control" value="<?= htmlspecialchars((string)($doctor['education'] ?? '')) ?>" maxlength="255">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" class="form-control" rows="2" maxlength="255"><?= htmlspecialchars((string)($doctor['address'] ?? '')) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Bio</label>
                <textarea name="bio" class="form-control" rows="4" maxlength="2000"><?= htmlspecialchars((string)($doctor['bio'] ?? '')) ?></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-4" style="gap: 10px;">
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="panel-card mb-4">
          <div class="fw-bold mb-3" style="font-size:16px; color:#111827;">Medical License</div>

          <div class="mb-3">
            <div class="field-label">License Number</div>
            <div class="fw-semibold text-dark"><?= htmlspecialchars($license_number !== '' ? $license_number : '-') ?></div>
          </div>

          <?php if ($verification === 'rejected' && !empty($doctor['verification_reason'])): ?>
            <div class="alert alert-danger mb-3">
              <strong>Reason:</strong> <?= htmlspecialchars((string)$doctor['verification_reason']) ?>
            </div>
          <?php endif; ?>

          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="field-label">License Document</div>
              <div class="text-muted" style="font-size: 13px;">
                <?= htmlspecialchars($license_doc !== '' ? basename(str_replace('\\', '/', $license_doc)) : 'Not uploaded') ?>
              </div>
            </div>

            <?php if ($license_doc !== ''): ?>
              <a class="btn btn-outline-primary btn-sm" href="doctor_license_view.php" target="_blank" rel="noopener">View</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="panel-card">
          <div class="fw-bold mb-3" style="font-size:16px; color:#111827;">Change Password</div>

          <form method="POST" autocomplete="off">
            <input type="hidden" name="action" value="change_password">

            <div class="mb-3">
              <label class="form-label fw-semibold">Current Password</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">New Password</label>
              <input type="password" name="new_password" class="form-control" minlength="6" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="6" required>
            </div>

            <div class="d-flex justify-content-end" style="gap: 10px;">
              <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Profile Image Upload Handler (submit to server)
    const profileImageInput = document.getElementById('profileImageInput');
    const profileImageForm = document.getElementById('profileImageForm');
    if (profileImageInput && profileImageForm) {
      profileImageInput.addEventListener('change', function() {
        if (profileImageInput.files && profileImageInput.files[0]) {
          profileImageForm.submit();
        }
      });
    }

    // Notifications dropdown (no Bootstrap JS required)
    (function() {
      const toggle = document.querySelector('[data-notif-toggle]');
      const menu = document.querySelector('[data-notif-menu]');
      if (!toggle || !menu) return;

      function closeMenu() {
        menu.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
      }

      toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        const willOpen = !menu.classList.contains('show');
        if (willOpen) {
          menu.classList.add('show');
          toggle.setAttribute('aria-expanded', 'true');
        } else {
          closeMenu();
        }
      });

      menu.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      document.addEventListener('click', function() {
        closeMenu();
      });

      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeMenu();
        }
      });
    })();

    // Doctor profile redirect (click name/photo)
    (function() {
      const profile = document.querySelector('.user-profile');
      if (!profile) return;

      function go() {
        window.location.href = 'doctor_profile.php';
      }

      profile.setAttribute('role', 'link');
      profile.setAttribute('tabindex', '0');
      profile.addEventListener('click', function() {
        go();
      });
      profile.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          go();
        }
      });
    })();
  </script>

</body>

</html>