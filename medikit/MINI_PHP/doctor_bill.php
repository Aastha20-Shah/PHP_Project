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

$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

$errors = [];
$success = '';

$booking = null;
$bill = null;

function fetch_booking_for_billing(mysqli $conn, int $doctor_id, int $booking_id): ?array
{
  $q = "
        SELECT
            vb.id AS booking_id,
            vb.patient_id,
            vb.doctor_id,
            vb.speciality_id,
            vb.time_id,
            vb.appointment_date,
            vb.status,
            vb.Note,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            p.phone_number,
            p.email AS patient_email,
            s.doctor_speciality,
            dat.start_time,
            dat.end_time
        FROM visit_booking vb
        LEFT JOIN patient p ON p.id = vb.patient_id
        LEFT JOIN speciality s ON s.id = vb.speciality_id
        LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
        WHERE vb.id = ? AND vb.doctor_id = ?
        LIMIT 1
    ";

  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("ii", $booking_id, $doctor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  return $row ?: null;
}

function fetch_bill_by_id(mysqli $conn, int $doctor_id, int $bill_id): ?array
{
  $q = "SELECT * FROM clinic_bills WHERE id = ? AND doctor_id = ? LIMIT 1";
  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("ii", $bill_id, $doctor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  return $row ?: null;
}

function fetch_bill_by_booking(mysqli $conn, int $doctor_id, int $booking_id): ?array
{
  $q = "SELECT * FROM clinic_bills WHERE booking_id = ? AND doctor_id = ? LIMIT 1";
  $stmt = $conn->prepare($q);
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param("ii", $booking_id, $doctor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  return $row ?: null;
}

if (empty($schema_error)) {
  if ($bill_id > 0) {
    $bill = fetch_bill_by_id($conn, $doctor_id, $bill_id);
    if ($bill) {
      $booking_id = (int)$bill['booking_id'];
    }
  }

  if ($booking_id > 0) {
    $booking = fetch_booking_for_billing($conn, $doctor_id, $booking_id);
    if (!$booking) {
      $errors[] = 'Booking not found.';
    } else {
      if (!$bill) {
        $bill = fetch_bill_by_booking($conn, $doctor_id, $booking_id);
      }

      if (($booking['status'] ?? '') !== 'visited') {
        $errors[] = 'Bill can be created only after marking the appointment as completed.';
      }
    }
  } else {
    $errors[] = 'Missing booking.';
  }
}

// Save bill
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($schema_error)) {
  $booking_id_post = (int)($_POST['booking_id'] ?? 0);
  $service_type = trim((string)($_POST['service_type'] ?? ''));
  $amount_raw = (string)($_POST['amount'] ?? '');
  $payment_method = billing_normalize_payment_method((string)($_POST['payment_method'] ?? 'Cash'));
  $payment_status = billing_normalize_payment_status((string)($_POST['payment_status'] ?? 'pending'));

  if ($booking_id_post <= 0) {
    $errors[] = 'Invalid booking.';
  } else {
    $booking = fetch_booking_for_billing($conn, $doctor_id, $booking_id_post);
    if (!$booking) {
      $errors[] = 'Booking not found.';
    } elseif (($booking['status'] ?? '') !== 'visited') {
      $errors[] = 'Bill can be created only after marking the appointment as completed.';
    }
  }

  if ($service_type === '') {
    $service_type = 'Consultation';
  }
  if (strlen($service_type) > 100) {
    $errors[] = 'Service type is too long.';
  }

  $amount = (float)$amount_raw;
  if (!is_numeric($amount_raw) || $amount <= 0) {
    $errors[] = 'Please enter a valid amount.';
  }

  if (empty($errors) && $booking) {
    $existing = fetch_bill_by_booking($conn, $doctor_id, (int)$booking['booking_id']);

    if ($existing) {
      $stmt = $conn->prepare("UPDATE clinic_bills SET service_type = ?, amount = ?, payment_method = ?, payment_status = ? WHERE id = ? AND doctor_id = ?");
      if ($stmt) {
        $bill_id_existing = (int)$existing['id'];
        $stmt->bind_param("sdssii", $service_type, $amount, $payment_method, $payment_status, $bill_id_existing, $doctor_id);
        $stmt->execute();
        $stmt->close();
        $bill_id = $bill_id_existing;
      }
    } else {
      $patient_id = (int)$booking['patient_id'];
      $stmt = $conn->prepare("INSERT INTO clinic_bills (booking_id, doctor_id, patient_id, service_type, amount, payment_method, payment_status) VALUES (?,?,?,?,?,?,?)");
      if ($stmt) {
        $stmt->bind_param("iiisdss", $booking_id_post, $doctor_id, $patient_id, $service_type, $amount, $payment_method, $payment_status);
        $stmt->execute();
        $bill_id = (int)$stmt->insert_id;
        $stmt->close();
      }
    }

    $bill = fetch_bill_by_id($conn, $doctor_id, $bill_id);
    $success = 'Bill saved successfully.';
  }
}

$invoice_no = $bill ? billing_invoice_no((int)$bill['id']) : '';

$prefill_service = $bill['service_type'] ?? 'Consultation';
$prefill_amount = $bill['amount'] ?? '';
$prefill_method = $bill['payment_method'] ?? 'Cash';
$prefill_status = $bill['payment_status'] ?? 'pending';

$time_label = '-';
$date_label = '-';
$patient_name = '-';
if ($booking) {
  $patient_name = trim(($booking['patient_firstname'] ?? '') . ' ' . ($booking['patient_lastname'] ?? ''));
  if (!empty($booking['appointment_date'])) {
    $date_label = date("F j, Y", strtotime((string)$booking['appointment_date']));
  }
  if (!empty($booking['start_time'])) {
    $time_label = date("g:i A", strtotime((string)$booking['start_time']));
  }
}

$method_options = [
  'Cash',
  'Credit Card',
  'Debit Card',
  'UPI',
  'Net Banking',
  'Insurance',
  'Other',
];

$redirect_qs = '';
if ($bill_id > 0) {
  $redirect_qs = '?bill_id=' . $bill_id;
} elseif ($booking_id > 0) {
  $redirect_qs = '?booking_id=' . $booking_id;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bill - Doctor Panel</title>

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

    .meta-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .meta-item {
      background: #f8fafc;
      border: 1px solid #eef2f7;
      border-radius: 14px;
      padding: 12px 14px;
    }

    .meta-label {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 2px;
    }

    .meta-value {
      font-size: 13px;
      color: #111827;
      font-weight: 600;
    }

    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
      }

      .sidebar {
        display: none;
      }

      .meta-grid {
        grid-template-columns: 1fr;
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
      <button class="menu-toggle" type="button" aria-label="Menu">
        <i class="fas fa-bars"></i>
      </button>
    </div>
    <div class="header-right">
      <button class="header-icon" type="button" aria-label="Fullscreen">
        <i class="fas fa-expand"></i>
      </button>
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
          <input type="hidden" name="redirect_qs" value="<?= htmlspecialchars($redirect_qs) ?>">
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
          <input type="hidden" name="redirect_qs" value="<?= htmlspecialchars($redirect_qs) ?>">
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
        <span>Bill</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-receipt"></i>
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
      <?php else: ?>
        <?php if (!empty($invoice_no)): ?>
          <div class="d-flex justify-content-between align-items-center mb-3" style="gap: 10px;">
            <div class="fw-semibold" style="color:#111827;">Invoice: #<?= htmlspecialchars($invoice_no) ?></div>
            <a href="doctor_billing.php" class="btn btn-sm btn-outline-secondary">Back to Billing</a>
          </div>
        <?php else: ?>
          <div class="d-flex justify-content-end mb-3">
            <a href="booking.php" class="btn btn-sm btn-outline-secondary">Back to Appointments</a>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($booking): ?>
          <div class="meta-grid mb-4">
            <div class="meta-item">
              <div class="meta-label">Patient</div>
              <div class="meta-value"><?= htmlspecialchars($patient_name !== '' ? $patient_name : '-') ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Appointment</div>
              <div class="meta-value"><?= htmlspecialchars($date_label) ?> • <?= htmlspecialchars($time_label) ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Speciality</div>
              <div class="meta-value"><?= htmlspecialchars((string)($booking['doctor_speciality'] ?? '-')) ?></div>
            </div>
            <div class="meta-item">
              <div class="meta-label">Notes</div>
              <div class="meta-value"><?= htmlspecialchars((string)($booking['Note'] ?? '-')) ?></div>
            </div>
          </div>

          <form method="POST">
            <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Service Type</label>
                <input type="text" name="service_type" class="form-control" value="<?= htmlspecialchars((string)$prefill_service) ?>" placeholder="Consultation">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Amount</label>
                <input type="number" name="amount" step="0.01" min="0" class="form-control" value="<?= htmlspecialchars((string)$prefill_amount) ?>" placeholder="0.00" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Payment Method (At Clinic)</label>
                <select name="payment_method" class="form-select">
                  <?php foreach ($method_options as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>" <?= (strcasecmp((string)$prefill_method, $opt) === 0) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($opt) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Payment Status</label>
                <select name="payment_status" class="form-select">
                  <option value="pending" <?= ((string)$prefill_status === 'pending') ? 'selected' : '' ?>>Pending</option>
                  <option value="paid" <?= ((string)$prefill_status === 'paid') ? 'selected' : '' ?>>Paid</option>
                </select>
              </div>
            </div>

            <div class="d-flex justify-content-end mt-4" style="gap: 10px;">
              <button type="submit" class="btn btn-primary">Save Bill</button>
            </div>
          </form>
        <?php endif; ?>
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