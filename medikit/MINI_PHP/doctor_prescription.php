<?php
session_start();
include("config.php");
include("prescription_helpers.php");
include("profile_image_helpers.php");
include("doctor_notification_helpers.php");
include_once("mailer_helpers.php");

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

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$saved = isset($_GET['saved']) ? (int)$_GET['saved'] : 0;
$docs_mail = isset($_GET['docs_mail']) ? (string)$_GET['docs_mail'] : '';

$errors = [];
$booking = null;
$prescription = null;

function fetch_booking_for_prescription(mysqli $conn, int $doctor_id, int $booking_id): ?array
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
            p.gender AS patient_gender,
            p.address AS patient_address,
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

function fetch_prescription_by_booking(mysqli $conn, int $doctor_id, int $booking_id): ?array
{
  $stmt = $conn->prepare("SELECT * FROM clinic_prescriptions WHERE booking_id = ? AND doctor_id = ? LIMIT 1");
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param('ii', $booking_id, $doctor_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  return $row ?: null;
}

if ($schema_error === '') {
  if ($booking_id <= 0) {
    $errors[] = 'Missing booking.';
  } else {
    $booking = fetch_booking_for_prescription($conn, $doctor_id, $booking_id);
    if (!$booking) {
      $errors[] = 'Booking not found.';
    } elseif (($booking['status'] ?? '') !== 'visited') {
      $errors[] = 'Prescription can be created only after marking the appointment as completed.';
    } else {
      $prescription = fetch_prescription_by_booking($conn, $doctor_id, $booking_id);
    }
  }
}

// Save prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schema_error === '') {
  $booking_id_post = (int)($_POST['booking_id'] ?? 0);

  if ($booking_id_post <= 0) {
    $errors[] = 'Invalid booking.';
  } else {
    $booking = fetch_booking_for_prescription($conn, $doctor_id, $booking_id_post);
    if (!$booking) {
      $errors[] = 'Booking not found.';
    } elseif (($booking['status'] ?? '') !== 'visited') {
      $errors[] = 'Prescription can be created only after marking the appointment as completed.';
    }
  }

  $diagnosis = trim((string)($_POST['diagnosis'] ?? ''));
  $instructions = trim((string)($_POST['instructions'] ?? ''));
  $status = prescriptions_normalize_status((string)($_POST['status'] ?? 'active'));

  $names = $_POST['medicine_name'] ?? [];
  $dosages = $_POST['medicine_dosage'] ?? [];
  $frequencies = $_POST['medicine_frequency'] ?? [];
  $durations = $_POST['medicine_duration'] ?? [];
  $times = $_POST['medicine_time'] ?? [];

  if (!is_array($names)) {
    $names = [];
  }
  if (!is_array($dosages)) {
    $dosages = [];
  }
  if (!is_array($frequencies)) {
    $frequencies = [];
  }
  if (!is_array($durations)) {
    $durations = [];
  }
  if (!is_array($times)) {
    $times = [];
  }

  $items = [];
  $max_rows = max(count($names), count($dosages), count($frequencies), count($durations), count($times));
  for ($i = 0; $i < $max_rows; $i++) {
    $medicine = trim((string)($names[$i] ?? ''));
    $d = trim((string)($dosages[$i] ?? ''));
    $f = trim((string)($frequencies[$i] ?? ''));
    $du = trim((string)($durations[$i] ?? ''));
    $t = trim((string)($times[$i] ?? ''));

    $all_empty = ($medicine === '' && $d === '' && $f === '' && $du === '' && $t === '');
    if ($all_empty) {
      continue;
    }

    if ($medicine === '') {
      $errors[] = 'Medicine name is required for row ' . ($i + 1) . '.';
      continue;
    }

    if (strlen($medicine) > 255) {
      $errors[] = 'Medicine name is too long for row ' . ($i + 1) . '.';
    }
    if (strlen($d) > 100) {
      $errors[] = 'Dosage is too long for row ' . ($i + 1) . '.';
    }
    if (strlen($f) > 100) {
      $errors[] = 'Frequency is too long for row ' . ($i + 1) . '.';
    }
    if (strlen($du) > 100) {
      $errors[] = 'Duration is too long for row ' . ($i + 1) . '.';
    }
    if (strlen($t) > 100) {
      $errors[] = 'Time is too long for row ' . ($i + 1) . '.';
    }

    $items[] = [
      'medicine' => $medicine,
      'dosage' => $d,
      'frequency' => $f,
      'duration' => $du,
      'time' => $t,
    ];
  }

  if (strlen($diagnosis) > 255) {
    $errors[] = 'Diagnosis is too long.';
  }

  if (empty($items)) {
    $errors[] = 'Please add at least one medicine.';
  }

  $medications = '';
  $dosage = '';
  $frequency = '';
  $duration = '';

  if (empty($errors)) {
    $encoded = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
      $errors[] = 'Unable to save medications.';
    } else {
      $medications = $encoded;
    }
  }

  if (empty($errors) && $booking) {
    $patient_id = (int)$booking['patient_id'];
    $existing = fetch_prescription_by_booking($conn, $doctor_id, $booking_id_post);

    if ($existing) {
      $stmt = $conn->prepare("UPDATE clinic_prescriptions SET diagnosis = ?, medications = ?, dosage = ?, frequency = ?, duration = ?, instructions = ?, status = ? WHERE booking_id = ? AND doctor_id = ?");
      if ($stmt) {
        $stmt->bind_param('sssssssii', $diagnosis, $medications, $dosage, $frequency, $duration, $instructions, $status, $booking_id_post, $doctor_id);
        $stmt->execute();
        $stmt->close();
      }
    } else {
      $stmt = $conn->prepare("INSERT INTO clinic_prescriptions (booking_id, doctor_id, patient_id, diagnosis, medications, dosage, frequency, duration, instructions, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
      if ($stmt) {
        $stmt->bind_param('iiisssssss', $booking_id_post, $doctor_id, $patient_id, $diagnosis, $medications, $dosage, $frequency, $duration, $instructions, $status);
        $stmt->execute();
        $stmt->close();
      }
    }

    $mailRes = medikit_send_patient_completed_documents_email_if_ready($conn, (int)$booking_id_post);
    $mailStatus = (string)($mailRes['status'] ?? '');
    $docs_mail_q = '';
    if ($mailStatus === 'sent') {
      $docs_mail_q = '&docs_mail=sent';
    } elseif ($mailStatus === 'pending_bill') {
      $docs_mail_q = '&docs_mail=pending_bill';
    } elseif ($mailStatus === 'mail_disabled') {
      $docs_mail_q = '&docs_mail=mail_disabled';
    } elseif ($mailStatus === 'send_failed') {
      $docs_mail_q = '&docs_mail=failed';
    }

    header('Location: doctor_prescription.php?booking_id=' . $booking_id_post . '&saved=1' . $docs_mail_q);
    exit;
  }
}

$patient_name = '-';
$date_label = '-';
$time_label = '-';
if ($booking) {
  $patient_name = trim((string)($booking['patient_firstname'] ?? '') . ' ' . (string)($booking['patient_lastname'] ?? ''));
  if (!empty($booking['appointment_date'])) {
    $date_label = date('F j, Y', strtotime((string)$booking['appointment_date']));
  }
  if (!empty($booking['start_time'])) {
    $time_label = date('g:i A', strtotime((string)$booking['start_time']));
  }
}

$prefill_diagnosis = trim((string)($_POST['diagnosis'] ?? ($prescription['diagnosis'] ?? '')));
$prefill_instructions = trim((string)($_POST['instructions'] ?? ($prescription['instructions'] ?? '')));
$prefill_status = prescriptions_normalize_status((string)($_POST['status'] ?? ($prescription['status'] ?? 'active')));

$prefill_items = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $names = $_POST['medicine_name'] ?? [];
  $dosages = $_POST['medicine_dosage'] ?? [];
  $frequencies = $_POST['medicine_frequency'] ?? [];
  $durations = $_POST['medicine_duration'] ?? [];
  $times = $_POST['medicine_time'] ?? [];

  if (!is_array($names)) {
    $names = [];
  }
  if (!is_array($dosages)) {
    $dosages = [];
  }
  if (!is_array($frequencies)) {
    $frequencies = [];
  }
  if (!is_array($durations)) {
    $durations = [];
  }
  if (!is_array($times)) {
    $times = [];
  }

  $max_rows = max(count($names), count($dosages), count($frequencies), count($durations), count($times));
  if ($max_rows <= 0) {
    $prefill_items[] = ['medicine' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'time' => ''];
  } else {
    for ($i = 0; $i < $max_rows; $i++) {
      $prefill_items[] = [
        'medicine' => trim((string)($names[$i] ?? '')),
        'dosage' => trim((string)($dosages[$i] ?? '')),
        'frequency' => trim((string)($frequencies[$i] ?? '')),
        'duration' => trim((string)($durations[$i] ?? '')),
        'time' => trim((string)($times[$i] ?? '')),
      ];
    }
  }
} elseif ($prescription) {
  $decoded_items = prescriptions_decode_medication_items((string)($prescription['medications'] ?? ''));
  if (is_array($decoded_items) && !empty($decoded_items)) {
    $prefill_items = $decoded_items;
  } else {
    $legacy_text = trim((string)($prescription['medications'] ?? ''));
    $legacy_lines = preg_split('/\r\n|\r|\n/', $legacy_text) ?: [];
    $legacy_dosage = trim((string)($prescription['dosage'] ?? ''));
    $legacy_frequency = trim((string)($prescription['frequency'] ?? ''));
    $legacy_duration = trim((string)($prescription['duration'] ?? ''));

    foreach ($legacy_lines as $line) {
      $line = trim((string)$line);
      if ($line === '') {
        continue;
      }
      $prefill_items[] = [
        'medicine' => $line,
        'dosage' => $legacy_dosage,
        'frequency' => $legacy_frequency,
        'duration' => $legacy_duration,
        'time' => '',
      ];
    }

    if (empty($prefill_items) && $legacy_text !== '') {
      $prefill_items[] = [
        'medicine' => $legacy_text,
        'dosage' => $legacy_dosage,
        'frequency' => $legacy_frequency,
        'duration' => $legacy_duration,
        'time' => '',
      ];
    }
  }
}

if (empty($prefill_items)) {
  $prefill_items[] = ['medicine' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'time' => ''];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prescription - Doctor Panel</title>

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

    /* NAVIGATION MENU */
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

    /* MAIN CONTENT */
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

    .form-label {
      font-weight: 600;
      font-size: 13px;
      color: #374151;
    }

    .btn-action {
      border-radius: 8px;
      padding: 8px 14px;
      font-weight: 600;
      font-size: 13px;
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
      <a href="doctor_analytics.php" class="nav-item">
        <i class="fas fa-chart-line"></i>
        <span>Analytics</span>
      </a>
      <a href="doctor_billing.php" class="nav-item active">
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
        <span>Prescription</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-file-prescription"></i>
      </div>
      <div class="breadcrumb-links">
        <a href="doctor_dashboard.php" style="color: #5a8dee; font-weight: 600; text-decoration: none;">Dashboard</a>
        <span>/</span>
        <span style="color: #5a8dee; font-weight: 600;">Prescription</span>
      </div>
    </div>

    <?php if ($schema_error !== ''): ?>
      <div class="alert alert-danger mx-3">Prescription table error: <?= htmlspecialchars($schema_error) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger mx-3">
        <div class="fw-semibold mb-1">Please fix the following:</div>
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($saved): ?>
      <div class="alert alert-success mx-3">
        Prescription saved successfully.
        <?php if ($docs_mail === 'sent'): ?>
          Bill &amp; prescription emailed to patient.
        <?php elseif ($docs_mail === 'pending_bill'): ?>
          (Bill not created yet; email will be sent after bill.)
        <?php elseif ($docs_mail === 'mail_disabled'): ?>
          (Email is disabled/not configured.)
        <?php elseif ($docs_mail === 'failed'): ?>
          (Could not email bill &amp; prescription.)
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="panel-card">
      <div class="panel-head">
        <div class="panel-title">Prescription Details</div>
        <div class="text-muted small">
          <span class="me-2"><i class="fas fa-user me-1"></i><?= htmlspecialchars($patient_name) ?></span>
          <span class="me-2"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($date_label) ?></span>
          <span><i class="fas fa-clock me-1"></i><?= htmlspecialchars($time_label) ?></span>
        </div>
      </div>

      <?php if ($booking): ?>
        <form method="POST" novalidate>
          <input type="hidden" name="booking_id" value="<?= (int)$booking['booking_id'] ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Diagnosis</label>
              <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($prefill_diagnosis) ?>" placeholder="e.g., Viral fever">
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?= $prefill_status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $prefill_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Medications <span class="text-danger">*</span></label>
              <div class="row g-2 text-muted small fw-semibold mb-1">
                <div class="col-md-3">Medicine</div>
                <div class="col-md-2">Dosage</div>
                <div class="col-md-2">Frequency</div>
                <div class="col-md-2">Duration</div>
                <div class="col-md-2">Time</div>
                <div class="col-md-1"></div>
              </div>

              <div id="medRows" class="d-flex flex-column gap-2">
                <?php foreach ($prefill_items as $it): ?>
                  <div class="row g-2 align-items-end" data-med-row>
                    <div class="col-md-3">
                      <input type="text" name="medicine_name[]" class="form-control" value="<?= htmlspecialchars((string)($it['medicine'] ?? '')) ?>" placeholder="e.g., Paracetamol" required>
                    </div>
                    <div class="col-md-2">
                      <input type="text" name="medicine_dosage[]" class="form-control" value="<?= htmlspecialchars((string)($it['dosage'] ?? '')) ?>" placeholder="e.g., 500mg">
                    </div>
                    <div class="col-md-2">
                      <input type="text" name="medicine_frequency[]" class="form-control" value="<?= htmlspecialchars((string)($it['frequency'] ?? '')) ?>" placeholder="e.g., 1-0-1">
                    </div>
                    <div class="col-md-2">
                      <input type="text" name="medicine_duration[]" class="form-control" value="<?= htmlspecialchars((string)($it['duration'] ?? '')) ?>" placeholder="e.g., 7 days">
                    </div>
                    <div class="col-md-2">
                      <input type="text" name="medicine_time[]" class="form-control" value="<?= htmlspecialchars((string)($it['time'] ?? '')) ?>" placeholder="e.g., After food">
                    </div>
                    <div class="col-md-1 d-grid">
                      <button type="button" class="btn btn-outline-danger btn-sm" data-med-remove aria-label="Remove"><i class="fas fa-trash"></i></button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="d-flex justify-content-end mt-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="addMedicineBtn"><i class="fas fa-plus me-1"></i> Add Medicine</button>
              </div>

              <template id="medicineRowTemplate">
                <div class="row g-2 align-items-end" data-med-row>
                  <div class="col-md-3">
                    <input type="text" name="medicine_name[]" class="form-control" placeholder="e.g., Paracetamol" required>
                  </div>
                  <div class="col-md-2">
                    <input type="text" name="medicine_dosage[]" class="form-control" placeholder="e.g., 500mg">
                  </div>
                  <div class="col-md-2">
                    <input type="text" name="medicine_frequency[]" class="form-control" placeholder="e.g., 1-0-1">
                  </div>
                  <div class="col-md-2">
                    <input type="text" name="medicine_duration[]" class="form-control" placeholder="e.g., 7 days">
                  </div>
                  <div class="col-md-2">
                    <input type="text" name="medicine_time[]" class="form-control" placeholder="e.g., After food">
                  </div>
                  <div class="col-md-1 d-grid">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-med-remove aria-label="Remove"><i class="fas fa-trash"></i></button>
                  </div>
                </div>
              </template>
            </div>

            <div class="col-12">
              <label class="form-label">Instructions</label>
              <textarea name="instructions" class="form-control" rows="3" placeholder="Additional advice / notes..."><?= htmlspecialchars($prefill_instructions) ?></textarea>
            </div>
          </div>

          <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a href="booking.php" class="btn btn-outline-secondary btn-action">Back</a>
            <a href="doctor_bill.php?booking_id=<?= (int)$booking['booking_id'] ?>" class="btn btn-outline-primary btn-action">Continue to Billing</a>
            <a href="doctor_view_prescription.php?booking_id=<?= (int)$booking['booking_id'] ?>" class="btn btn-outline-dark btn-action">View</a>
            <button type="submit" class="btn btn-success btn-action"><i class="fas fa-save me-1"></i> Save</button>
          </div>
        </form>
      <?php else: ?>
        <div class="text-muted">Prescription is not available.</div>
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

    // Medications rows (add/remove)
    (function() {
      const container = document.getElementById('medRows');
      const addBtn = document.getElementById('addMedicineBtn');
      const tpl = document.getElementById('medicineRowTemplate');
      if (!container || !addBtn || !tpl) return;

      addBtn.addEventListener('click', function() {
        const row = tpl.content.firstElementChild.cloneNode(true);
        container.appendChild(row);
        const input = row.querySelector('input[name="medicine_name[]"]');
        if (input) input.focus();
      });

      container.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-med-remove]');
        if (!btn) return;

        const row = btn.closest('[data-med-row]');
        if (!row) return;

        const rows = container.querySelectorAll('[data-med-row]');
        if (rows.length <= 1) {
          row.querySelectorAll('input').forEach(function(i) {
            i.value = '';
          });
          return;
        }

        row.remove();
      });
    })();
  </script>
</body>

</html>