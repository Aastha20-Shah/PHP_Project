<?php
session_start();
include("config.php");
include("prescription_helpers.php");
include("profile_image_helpers.php");
include("doctor_notification_helpers.php");

medikit_ensure_profile_image_schema($conn);

if (!isset($_SESSION['doctor_id'])) {
  header("Location: login.php");
  exit;
}

$doctor_id = (int)$_SESSION['doctor_id'];

$notifications = medikit_doctor_unseen_notifications_list($conn, $doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, $doctor_id);

$schema_error = '';
try {
  prescriptions_ensure_schema($conn);
} catch (Throwable $e) {
  $schema_error = $e->getMessage();
}

// Doctor info
$doctor_res = mysqli_query($conn, "SELECT firstname, lastname, profile_image, clinic_name, address AS clinic_address, phone_number AS clinic_phone, email AS clinic_email FROM users WHERE id = $doctor_id LIMIT 1");
$doctor = $doctor_res ? mysqli_fetch_assoc($doctor_res) : null;
if (!$doctor) {
  $doctor = ['firstname' => 'Doctor', 'lastname' => '', 'profile_image' => '', 'clinic_name' => '', 'clinic_address' => '', 'clinic_phone' => '', 'clinic_email' => ''];
}
$doctor['profile_image'] = $doctor['profile_image'] ?? '';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$rx = null;
if ($schema_error === '' && $booking_id > 0) {
  $q = "
        SELECT
            cp.*, 
            vb.appointment_date,
            vb.Note,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            p.phone_number AS patient_phone,
            p.email AS patient_email,
            p.gender AS patient_gender,
            p.address AS patient_address
        FROM clinic_prescriptions cp
        LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
        LEFT JOIN patient p ON p.id = cp.patient_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE cp.booking_id = ? AND cp.doctor_id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if ($stmt) {
    $stmt->bind_param('ii', $booking_id, $doctor_id);
    $stmt->execute();
    $rx = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}

$rx_id = $rx ? (int)$rx['id'] : 0;
$rx_code = $rx_id > 0 ? prescriptions_code($rx_id) : '';

$patient_name = $rx ? trim((string)($rx['patient_firstname'] ?? '') . ' ' . (string)($rx['patient_lastname'] ?? '')) : '';
$appt_date = '-';
$appt_time = '-';
if (!empty($rx['appointment_date'])) {
  $appt_date = date('F j, Y', strtotime((string)$rx['appointment_date']));
}
if (!empty($rx['start_time'])) {
  $appt_time = date('g:i A', strtotime((string)$rx['start_time']));
}

$clinic_name = trim((string)($doctor['clinic_name'] ?? ''));
$clinic_address = trim((string)($doctor['clinic_address'] ?? ''));
$clinic_phone = trim((string)($doctor['clinic_phone'] ?? ''));
$clinic_email = trim((string)($doctor['clinic_email'] ?? ''));

$med_items = null;
if ($rx) {
  $med_items = prescriptions_decode_medication_items((string)($rx['medications'] ?? ''));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Prescription - Doctor Panel</title>

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

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 22px;
      font-weight: 700;
      color: #333;
      margin-left: 25px;
    }

    .logo i {
      color: #ff6b6b;
      font-size: 28px;
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
      padding: 8px 15px;
      border-radius: 10px;
      background: #f8f9fa;
      cursor: pointer;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #fff;
    }

    .user-name {
      font-size: 14px;
      font-weight: 600;
      color: #333;
    }

    .flag-icon {
      width: 30px;
      height: 20px;
      border-radius: 3px;
      object-fit: cover;
    }

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

    .nav-item:hover {
      background: #f8f9fa;
      color: #5a8dee;
    }

    .nav-item.active {
      background: #e8f0fe;
      color: #5a8dee;
      font-weight: 500;
    }

    .nav-item i {
      width: 20px;
      font-size: 16px;
    }

    .main-content {
      margin-left: 280px;
      padding-top: 60px;
      min-height: 100vh;
    }

    .breadcrumb-bar {
      background: #ffffff;
      padding: 18px 25px;
      margin: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .breadcrumb-title {
      font-size: 18px;
      font-weight: 700;
      color: #333;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .breadcrumb-links {
      color: #9ca3af;
      font-size: 13px;
    }

    .breadcrumb-links span {
      margin: 0 5px;
    }

    .panel-card {
      background: #fff;
      margin: 0 20px 20px;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      padding: 20px;
    }

    .rx-meta {
      font-size: 13px;
      color: #6b7280;
    }

    .rx-block-title {
      font-weight: 700;
      color: #111827;
      font-size: 13px;
      margin-bottom: 6px;
    }

    .rx-pre {
      white-space: pre-wrap;
      background: #f8fafc;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 12px;
      font-size: 13px;
      color: #111827;
    }

    @media print {

      .top-header,
      .sidebar,
      .breadcrumb-bar,
      .rx-actions {
        display: none !important;
      }

      .main-content {
        margin-left: 0 !important;
        padding-top: 0 !important;
      }

      body {
        background: #fff !important;
      }

      .panel-card {
        box-shadow: none !important;
        margin: 0 !important;
        border-radius: 0 !important;
      }
    }
  </style>
</head>

<body>

  <div class="top-header">
    <div class="logo">
      <i class="fa-solid fa-stethoscope"></i>
      <span>Medkit</span>
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
        <span class="user-name"><?= htmlspecialchars((string)$doctor['firstname'] . ' ' . (string)$doctor['lastname']) ?></span>
        <?php
        $doctor_avatar_src = (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image']))
          ? $doctor['profile_image']
          : "https://ui-avatars.com/api/?name=" . urlencode((string)$doctor['firstname'] . '+' . (string)$doctor['lastname']) . "&background=5a8dee&color=fff";
        ?>
        <img src="<?= htmlspecialchars($doctor_avatar_src) ?>" alt="Profile" class="user-avatar">
      </a>
    </div>
  </div>

  <div class="sidebar">
    <div class="doctor-profile-sidebar">
      <div class="profile-image-wrapper">
        <form id="profileImageForm" action="doctor_profile_image.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
          <input type="file" name="profile_image" id="profileImageInput" accept="image/*" style="display: none;">
          <label for="profileImageInput" style="cursor: pointer; display: block; width: 115px; height: 115px; margin: 0 auto;">
            <div class="profile-image-container" id="sidebarProfileImage">
              <?php if (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image'])): ?>
                <img src="<?= htmlspecialchars($doctor['profile_image']) ?>" alt="Profile" class="profile-image">
              <?php else: ?>
                <div class="profile-placeholder"><?= strtoupper(substr((string)$doctor['firstname'], 0, 1)) ?></div>
              <?php endif; ?>
            </div>
          </label>
        </form>
      </div>
      <?php if (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image'])): ?>
        <form method="POST" action="doctor_profile_image.php" class="text-center mt-2" onsubmit="return confirm('Remove profile photo?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
        </form>
      <?php endif; ?>
      <div class="profile-name"><?= htmlspecialchars((string)$doctor['firstname'] . ' ' . (string)$doctor['lastname']) ?></div>
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
      <a href="doctor_prescriptions.php" class="nav-item active">
        <i class="fas fa-file-prescription"></i>
        <span>Prescriptions</span>
      </a>
      <a href="logout.php" class="nav-item">
        <i class="fas fa-right-from-bracket"></i>
        <span>Logout</span>
      </a>
    </nav>
  </div>

  <div class="main-content">
    <div class="breadcrumb-bar">
      <div class="breadcrumb-title">
        <span>Prescription</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-file-prescription"></i>
      </div>
      <div class="breadcrumb-links">
        <a href="doctor_prescriptions.php" style="color: #5a8dee; font-weight: 600; text-decoration: none;">Prescriptions</a>
        <span>/</span>
        <span style="color: #5a8dee; font-weight: 600;">View</span>
      </div>
    </div>

    <?php if ($schema_error !== ''): ?>
      <div class="alert alert-danger mx-3">Prescription table error: <?= htmlspecialchars($schema_error) ?></div>
    <?php elseif (!$rx): ?>
      <div class="alert alert-warning mx-3">Prescription not found.</div>
    <?php endif; ?>

    <?php if ($rx): ?>
      <div class="panel-card" id="rx-print-area">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap: 12px;">
          <div>
            <div class="text-muted">Prescription</div>
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($rx_code) ?></h4>
            <div class="rx-meta">Created: <?= htmlspecialchars(date('F j, Y', strtotime((string)$rx['created_at']))) ?></div>
          </div>
          <div class="text-end">
            <span class="badge rounded-pill fs-6 <?= ($rx['status'] ?? '') === 'inactive' ? 'bg-secondary' : 'bg-success' ?>"><?= htmlspecialchars(ucfirst((string)($rx['status'] ?? 'active'))) ?></span>
          </div>
        </div>

        <hr class="my-4">

        <div class="row g-3">
          <div class="col-md-6">
            <div class="rx-block-title">Doctor</div>
            <div>Dr. <?= htmlspecialchars((string)$doctor['firstname'] . ' ' . (string)$doctor['lastname']) ?></div>
            <div class="text-primary fw-bold"><?= htmlspecialchars((string)($rx['doctor_speciality'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="rx-block-title">Appointment</div>
            <div class="text-muted"><i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($appt_date) ?></div>
            <div class="text-muted"><i class="fas fa-clock me-2"></i><?= htmlspecialchars($appt_time) ?></div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <div class="rx-block-title">Patient</div>
            <div><?= htmlspecialchars($patient_name !== '' ? $patient_name : '-') ?></div>
            <div class="text-muted small">
              <?php if (!empty($rx['patient_phone'])): ?>
                <div><strong>Phone:</strong> <?= htmlspecialchars((string)$rx['patient_phone']) ?></div>
              <?php endif; ?>
              <?php if (!empty($rx['patient_email'])): ?>
                <div><strong>Email:</strong> <?= htmlspecialchars((string)$rx['patient_email']) ?></div>
              <?php endif; ?>
              <?php if (!empty($rx['patient_gender'])): ?>
                <div><strong>Gender:</strong> <?= htmlspecialchars((string)$rx['patient_gender']) ?></div>
              <?php endif; ?>
              <?php if (!empty($rx['patient_address'])): ?>
                <div><strong>Address:</strong> <?= htmlspecialchars((string)$rx['patient_address']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="rx-block-title">Clinic Info</div>
            <div class="text-muted small">
              <?php if ($clinic_name !== ''): ?>
                <div><strong>Clinic:</strong> <?= htmlspecialchars($clinic_name) ?></div>
              <?php endif; ?>
              <?php if ($clinic_address !== ''): ?>
                <div><strong>Address:</strong> <?= htmlspecialchars($clinic_address) ?></div>
              <?php endif; ?>
              <?php if ($clinic_phone !== ''): ?>
                <div><strong>Phone:</strong> <?= htmlspecialchars($clinic_phone) ?></div>
              <?php endif; ?>
              <?php if ($clinic_email !== ''): ?>
                <div><strong>Email:</strong> <?= htmlspecialchars($clinic_email) ?></div>
              <?php endif; ?>
              <?php if ($clinic_name === '' && $clinic_address === '' && $clinic_phone === '' && $clinic_email === ''): ?>
                <div>-</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <hr class="my-4">

        <div class="row g-3">
          <div class="col-md-6">
            <div class="rx-block-title">Diagnosis</div>
            <div><?= htmlspecialchars((string)($rx['diagnosis'] ?? '')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="rx-block-title">Schedule</div>
            <div class="text-muted small">
              <?php if (is_array($med_items) && !empty($med_items)): ?>
                <div>See medicine schedule below.</div>
              <?php else: ?>
                <div><strong>Dosage:</strong> <?= htmlspecialchars((string)($rx['dosage'] ?? '')) ?></div>
                <div><strong>Frequency:</strong> <?= htmlspecialchars((string)($rx['frequency'] ?? '')) ?></div>
                <div><strong>Duration:</strong> <?= htmlspecialchars((string)($rx['duration'] ?? '')) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-12">
            <div class="rx-block-title">Medications</div>
            <?php if (is_array($med_items) && !empty($med_items)): ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Medicine</th>
                      <th>Dosage</th>
                      <th>Frequency</th>
                      <th>Duration</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($med_items as $it): ?>
                      <?php
                      $m = trim((string)($it['medicine'] ?? ''));
                      $d = trim((string)($it['dosage'] ?? ''));
                      $f = trim((string)($it['frequency'] ?? ''));
                      $du = trim((string)($it['duration'] ?? ''));
                      $t = trim((string)($it['time'] ?? ''));
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($m !== '' ? $m : '-') ?></td>
                        <td><?= htmlspecialchars($d !== '' ? $d : '-') ?></td>
                        <td><?= htmlspecialchars($f !== '' ? $f : '-') ?></td>
                        <td><?= htmlspecialchars($du !== '' ? $du : '-') ?></td>
                        <td><?= htmlspecialchars($t !== '' ? $t : '-') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="rx-pre"><?= htmlspecialchars((string)($rx['medications'] ?? '')) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-12">
            <div class="rx-block-title">Instructions</div>
            <div class="rx-pre"><?= htmlspecialchars((string)($rx['instructions'] ?? '')) ?></div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 rx-actions">
          <a href="doctor_prescriptions.php" class="btn btn-outline-secondary">Back</a>
          <a href="doctor_prescription.php?booking_id=<?= (int)$rx['booking_id'] ?>" class="btn btn-outline-primary">Edit</a>
          <button type="button" class="btn btn-dark" onclick="window.print()"><i class="fas fa-download me-1"></i> Download</button>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const profileImageInput = document.getElementById('profileImageInput');
    const profileImageForm = document.getElementById('profileImageForm');
    if (profileImageInput && profileImageForm) {
      profileImageInput.addEventListener('change', function() {
        if (profileImageInput.files && profileImageInput.files[0]) {
          profileImageForm.submit();
        }
      });
    }

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
  </script>
</body>

</html>