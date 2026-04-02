<?php
session_start();
include("config.php");
include("billing_helpers.php");
include("profile_image_helpers.php");
include("doctor_notification_helpers.php");

if (!isset($_SESSION['doctor_id'])) {
  header("Location: login.php");
  exit;
}

$doctor_id = (int)$_SESSION['doctor_id'];

$notifications = medikit_doctor_unseen_notifications_list($conn, $doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, $doctor_id);

try {
  billing_ensure_schema($conn);
} catch (Throwable $e) {
  $schema_error = $e->getMessage();
}

medikit_ensure_profile_image_schema($conn);

// Doctor info (for dashboard header/sidebar)
$doctor_res = mysqli_query($conn, "SELECT firstname, lastname, profile_image FROM users WHERE id = $doctor_id LIMIT 1");
$doctor = $doctor_res ? mysqli_fetch_assoc($doctor_res) : null;
if (!$doctor) {
  $doctor = ['firstname' => 'Doctor', 'lastname' => '', 'profile_image' => ''];
}

$doctor['profile_image'] = $doctor['profile_image'] ?? '';

$bills = [];
if (empty($schema_error)) {
  $query = "
        SELECT
            cb.id AS bill_id,
            cb.booking_id,
            cb.service_type,
            cb.amount,
            cb.payment_method,
            cb.payment_status,
            vb.appointment_date,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname
        FROM clinic_bills cb
        JOIN visit_booking vb ON vb.id = cb.booking_id
        LEFT JOIN patient p ON p.id = cb.patient_id
        WHERE cb.doctor_id = ?
        ORDER BY cb.created_at DESC, cb.id DESC
    ";

  $stmt = $conn->prepare($query);
  if ($stmt) {
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
      while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
      }
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
  <title>Billing - Doctor Panel</title>

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
      width: 265px;
      position: fixed;
      left: 0;
      top: 60px;
      bottom: 0;
      background: #ffffff;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
      overflow-y: auto;
      z-index: 1000;
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

    .table thead th {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #8b8b8b;
      border-bottom: 1px solid #f0f0f0 !important;
    }

    .table td {
      font-size: 13px;
      color: #4b5563;
      vertical-align: middle;
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

    .badge-paid {
      background: #ecfdf5;
      color: #059669;
    }

    .badge-pending {
      background: #fff7ed;
      color: #ea580c;
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
        <span>Medikit</span>
      </div>
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
      <div class="user-profile">
        <span class="user-name"><?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></span>
        <?php
        $doctor_avatar_src = (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image']))
          ? $doctor['profile_image']
          : "https://ui-avatars.com/api/?name=" . urlencode($doctor['firstname'] . '+' . $doctor['lastname']) . "&background=5a8dee&color=fff";
        ?>
        <img src="<?= htmlspecialchars($doctor_avatar_src) ?>" alt="Profile" class="user-avatar">
      </div>
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
              <?php if (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image'])): ?>
                <img src="<?= htmlspecialchars($doctor['profile_image']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; display: block;">
              <?php else: ?>
                <div class="profile-placeholder">
                  <?= strtoupper(substr($doctor['firstname'] ?: 'D', 0, 1)) ?>
                </div>
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
      <a href="#" class="nav-item">
        <i class="fas fa-chart-line"></i>
        <span>Analytics</span>
      </a>
      <a href="doctor_billing.php" class="nav-item active">
        <i class="fas fa-file-invoice-dollar"></i>
        <span>Billing</span>
      </a>
      <a href="#" class="nav-item">
        <i class="fas fa-file-medical"></i>
        <span>Medical Certificates</span>
      </a>
      <a href="#" class="nav-item">
        <i class="fas fa-notes-medical"></i>
        <span>Consultations Note</span>
      </a>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="breadcrumb-bar">
      <div class="breadcrumb-title">
        <span>Billing</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-file-invoice-dollar"></i>
      </div>
    </div>

    <?php if (isset($_SESSION['doctor_photo_message'])): ?>
      <div class="alert alert-<?= htmlspecialchars($_SESSION['doctor_photo_message_type'] ?? 'info') ?> alert-dismissible fade show mx-3" role="alert">
        <?= htmlspecialchars($_SESSION['doctor_photo_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <?php unset($_SESSION['doctor_photo_message'], $_SESSION['doctor_photo_message_type']); ?>
    <?php endif; ?>

    <div class="panel-card">
      <?php if (!empty($schema_error)): ?>
        <div class="alert alert-danger mb-0">
          Billing table error: <?= htmlspecialchars($schema_error) ?>
        </div>
      <?php elseif (empty($bills)): ?>
        <div class="text-center text-muted py-4">No bills created yet.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Invoice ID</th>
                <th>Patient Name</th>
                <th>Consultation Date</th>
                <th>Service Type</th>
                <th>Amount</th>
                <th>Payment Method</th>
                <th>Status</th>
                <th style="min-width: 120px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bills as $bill): ?>
                <?php
                $invoice_no = billing_invoice_no((int)($bill['bill_id'] ?? 0));
                $patient_name = trim(($bill['patient_firstname'] ?? '') . ' ' . ($bill['patient_lastname'] ?? ''));
                $consult_date = '-';
                if (!empty($bill['appointment_date'])) {
                  $consult_date = date("m/d/Y", strtotime($bill['appointment_date']));
                }

                $pay_status = (string)($bill['payment_status'] ?? 'pending');
                $badge_class = $pay_status === 'paid' ? 'badge-soft badge-paid' : 'badge-soft badge-pending';
                $badge_text = $pay_status === 'paid' ? 'Paid' : 'Pending';
                ?>
                <tr>
                  <td><span class="fw-semibold">#<?= htmlspecialchars($invoice_no) ?></span></td>
                  <td><?= htmlspecialchars($patient_name !== '' ? $patient_name : '-') ?></td>
                  <td><?= htmlspecialchars($consult_date) ?></td>
                  <td><?= htmlspecialchars((string)($bill['service_type'] ?? 'Consultation')) ?></td>
                  <td><?= htmlspecialchars(number_format((float)($bill['amount'] ?? 0), 2)) ?></td>
                  <td><?= htmlspecialchars((string)($bill['payment_method'] ?? 'Cash')) ?></td>
                  <td><span class="<?= $badge_class ?>"><?= htmlspecialchars($badge_text) ?></span></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="doctor_bill.php?bill_id=<?= (int)$bill['bill_id'] ?>">View</a>
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
  </script>

</body>

</html>