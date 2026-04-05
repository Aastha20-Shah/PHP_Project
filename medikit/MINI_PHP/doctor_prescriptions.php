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

// Doctor info (for dashboard header/sidebar)
$doctor_res = mysqli_query($conn, "SELECT firstname, lastname, profile_image FROM users WHERE id = $doctor_id LIMIT 1");
$doctor = $doctor_res ? mysqli_fetch_assoc($doctor_res) : null;
if (!$doctor) {
  $doctor = ['firstname' => 'Doctor', 'lastname' => '', 'profile_image' => ''];
}
$doctor['profile_image'] = $doctor['profile_image'] ?? '';

$rows = [];
if ($schema_error === '') {
  $q = "
        SELECT
            cp.id AS prescription_id,
            cp.booking_id,
            cp.status,
            cp.created_at,
            vb.appointment_date,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            s.doctor_speciality
        FROM clinic_prescriptions cp
        LEFT JOIN visit_booking vb ON vb.id = cp.booking_id
        LEFT JOIN patient p ON p.id = cp.patient_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        WHERE cp.doctor_id = ?
        ORDER BY cp.created_at DESC, cp.id DESC
    ";

  $stmt = $conn->prepare($q);
  if ($stmt) {
    $stmt->bind_param('i', $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r = $res->fetch_assoc())) {
      $rows[] = $r;
    }
    $stmt->close();
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescriptions - Doctor Panel</title>

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

    /* SIDEBAR (match Billing/Patients pages) */
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

    .panel-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .panel-title {
      font-size: 16px;
      font-weight: 700;
      color: #111827;
    }

    .table td {
      font-size: 13px;
      color: #4b5563;
      vertical-align: middle;
    }

    .cell-truncate {
      max-width: 220px;
      display: inline-block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      vertical-align: bottom;
    }

    .btn-action {
      border-radius: 8px;
      padding: 6px 12px;
      font-weight: 600;
      font-size: 12px;
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
        <span class="user-name"><?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></span>
        <?php
        $doctor_avatar_src = (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image']))
          ? $doctor['profile_image']
          : "https://ui-avatars.com/api/?name=" . urlencode($doctor['firstname'] . '+' . $doctor['lastname']) . "&background=5a8dee&color=fff";
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
                <div class="profile-placeholder"><?= strtoupper(substr($doctor['firstname'], 0, 1)) ?></div>
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
      <div class="profile-name"><?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></div>
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
        <span>Prescriptions</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-file-prescription"></i>
      </div>
      <div class="breadcrumb-links">
        <a href="doctor_dashboard.php" style="color: #5a8dee; font-weight: 600; text-decoration: none;">Dashboard</a>
        <span>/</span>
        <span style="color: #5a8dee; font-weight: 600;">Prescriptions</span>
      </div>
    </div>

    <?php if ($schema_error !== ''): ?>
      <div class="alert alert-danger mx-3">Prescription table error: <?= htmlspecialchars($schema_error) ?></div>
    <?php endif; ?>

    <div class="panel-card">
      <div class="panel-head">
        <div class="panel-title">My Prescriptions</div>
        <div style="max-width: 280px; width: 100%;">
          <input type="text" id="rxSearch" class="form-control form-control-sm" placeholder="Search prescriptions...">
        </div>
      </div>

      <?php if (empty($rows)): ?>
        <div class="text-center text-muted py-4">No prescriptions found.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="rxTable">
            <thead>
              <tr>
                <th>RX</th>
                <th>Patient</th>
                <th>Speciality</th>
                <th>Appointment Date</th>
                <th>Created</th>
                <th>Status</th>
                <th style="min-width: 180px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <?php
                $rx_id = (int)($r['prescription_id'] ?? 0);
                $rx_code = $rx_id > 0 ? prescriptions_code($rx_id) : '-';
                $patient = trim((string)($r['patient_firstname'] ?? '') . ' ' . (string)($r['patient_lastname'] ?? ''));
                $appt_date = '-';
                if (!empty($r['appointment_date'])) {
                  $appt_date = date('m/d/Y', strtotime((string)$r['appointment_date']));
                }
                $created = '-';
                if (!empty($r['created_at'])) {
                  $created = date('m/d/Y', strtotime((string)$r['created_at']));
                }
                $status = (string)($r['status'] ?? 'active');
                $status_badge = $status === 'inactive' ? 'bg-secondary' : 'bg-success';
                ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($rx_code) ?></td>
                  <td>
                    <span class="cell-truncate" title="<?= htmlspecialchars($patient) ?>"><?= htmlspecialchars($patient !== '' ? $patient : '-') ?></span>
                  </td>
                  <td>
                    <span class="cell-truncate" title="<?= htmlspecialchars((string)($r['doctor_speciality'] ?? '')) ?>"><?= htmlspecialchars((string)($r['doctor_speciality'] ?? '-')) ?></span>
                  </td>
                  <td><?= htmlspecialchars($appt_date) ?></td>
                  <td><?= htmlspecialchars($created) ?></td>
                  <td><span class="badge rounded-pill <?= htmlspecialchars($status_badge) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                  <td>
                    <a class="btn btn-outline-dark btn-sm btn-action" href="doctor_view_prescription.php?booking_id=<?= (int)$r['booking_id'] ?>">View</a>
                    <a class="btn btn-outline-primary btn-sm btn-action" href="doctor_prescription.php?booking_id=<?= (int)$r['booking_id'] ?>">Edit</a>
                    <a class="btn btn-outline-secondary btn-sm btn-action" href="doctor_bill.php?booking_id=<?= (int)$r['booking_id'] ?>">Bill</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function() {
      const input = document.getElementById('rxSearch');
      const table = document.getElementById('rxTable');
      if (!input || !table) return;

      input.addEventListener('input', function() {
        const term = (input.value || '').toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach((row) => {
          const text = (row.innerText || '').toLowerCase();
          row.style.display = text.includes(term) ? '' : 'none';
        });
      });
    })();

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