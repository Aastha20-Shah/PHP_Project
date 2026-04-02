<?php
session_start();
include("config.php");
include("profile_image_helpers.php");
include("doctor_notification_helpers.php");

medikit_ensure_profile_image_schema($conn);

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

$notifications = medikit_doctor_unseen_notifications_list($conn, (int)$doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, (int)$doctor_id);

/* =======================
   DASHBOARD COUNTS
======================= */

// Total Patients
$totalPatients = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT patient_id) AS total
     FROM visit_booking
     WHERE doctor_id = $doctor_id"
))['total'] ?? 0;

// Today Appointments
$todayAppointments = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM visit_booking
     WHERE doctor_id = $doctor_id
     AND appointment_date = CURDATE()"
))['total'] ?? 0;

// Pending
$pendingAppointments = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM visit_booking
     WHERE doctor_id = $doctor_id
     AND status = 'pending'"
))['total'] ?? 0;

// Visited
$visitedAppointments = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM visit_booking
     WHERE doctor_id = $doctor_id
     AND status = 'visited'"
))['total'] ?? 0;

// Doctor Info
$doctor = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT u.firstname, u.lastname, u.profile_image, u.email, u.category_id,
     GROUP_CONCAT(s.doctor_speciality SEPARATOR ', ') AS specialities,
     COUNT(ds.id) as speciality_count
     FROM users u
     LEFT JOIN doctor_speciality ds ON ds.doctor_id = u.id
     LEFT JOIN speciality s ON s.id = ds.speciality_id
     WHERE u.id = $doctor_id
     GROUP BY u.id"
));

if (is_array($doctor)) {
    // DB schema does not include a profile_image column; keep the UI checks safe.
    $doctor['profile_image'] = $doctor['profile_image'] ?? '';
}

// Check if profile setup is needed
$needsProfileSetup = !$doctor['category_id'] || $doctor['speciality_count'] == 0;

// Fetch all categories
$categories_result = mysqli_query($conn, "SELECT id, category_name FROM category ORDER BY category_name ASC");
$categories = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Medikit</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
            background: #f7f9fd;
            padding: 18px 22px;
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid #e6edf7;
            box-shadow: 0 8px 24px rgba(31, 42, 68, 0.06);
            position: relative;
            z-index: 1;
        }

        .breadcrumb-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2a44;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .breadcrumb-links {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        /* WELCOME SECTION */
        .welcome-card {
            background: linear-gradient(135deg, #ffffff 0%, #f5f8ff 100%);
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 20px;
            width: 65%;
            max-width: 950px;
            box-shadow: 0 12px 34px rgba(31, 42, 68, 0.1);
            border: 1px solid #e7eef8;
            position: relative;
            overflow: visible;
            z-index: 1;
        }

        .welcome-card .col-md-8 {
            padding-right: 20px;
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            right: -50px;
            top: -40px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(255, 107, 107, 0.18), rgba(255, 107, 107, 0));
        }

        .welcome-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .doctor-name-large {
            font-size: 28px;
            font-weight: 700;
            color: #2a66e3;
            margin-bottom: 5px;
        }

        .speciality {
            font-size: 14px;
            color: #666;
        }

        /* STAT BOXES */
        .stat-boxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 18px;
        }

        .stat-box {
            padding: 18px 22px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(31, 42, 68, 0.08);
            box-shadow: 0 8px 20px rgba(31, 42, 68, 0.08);
            min-width: 150px;

        }

        .stat-box.purple {
            background: linear-gradient(135deg, #e0e6ff 0%, #f1f4ff 100%);
        }

        .stat-box.pink {
            background: linear-gradient(135deg, #ffdede 0%, #fff1f1 100%);
        }

        .stat-box.green {
            background: linear-gradient(135deg, #dff9f2 0%, #effbf7 100%);
        }

        .stat-box-label {
            font-size: 13px;
            color: #5c6b82;
            margin-bottom: 5px;
        }

        .stat-box-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2a44;
        }

        .doctor-illustration {
            max-height: 240px;
            width: auto;
            display: block;
            margin-left: auto;
        }

        /* CONTENT GRID */
        .content-wrapper {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 25px;
            position: relative;
            z-index: 1;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 12px 28px rgba(31, 42, 68, 0.08);
            border: 1px solid #e6edf7;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 34px rgba(31, 42, 68, 0.12);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1f2a44;
            margin-bottom: 20px;
        }

        /* RIGHT SIDEBAR PROFILE */
        .right-profile-card {
            position: sticky;
            margin-top: -290px;
        }

        .profile-card-image {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid #eff4ff;
            object-fit: cover;
        }

        .profile-card-placeholder {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            margin: 0 auto 20px;
            border: 5px solid #eff4ff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 52px;
            font-weight: 700;
        }

        .profile-card-name {
            font-size: 22px;
            font-weight: 600;
            color: #2a66e3;
            text-align: center;
            margin-bottom: 5px;
        }

        .profile-card-spec {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .profile-stats-box {
            background: #f6f8fc;
            padding: 20px;
            border-radius: 14px;
            margin-bottom: 15px;
            border: 1px solid #e6edf7;
        }

        .profile-stat-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            text-align: center;
        }

        .profile-stat-item {
            padding: 15px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #edf2fa;
        }

        .profile-stat-value {
            font-size: 22px;
            font-weight: 700;
            color: #333;
        }

        .profile-stat-label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .percentage-circle {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }

        .percentage-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            font-weight: 700;
            color: #ff6b6b;
        }

        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }
        }

        /* Profile Setup Modal Styling */
        #profileSetupModal .modal-content {
            border-radius: 18px;
            border: none;
            box-shadow: 0 20px 60px rgba(31, 42, 68, 0.25);
        }

        #profileSetupModal .modal-header {
            background: linear-gradient(135deg, #5a8dee 0%, #7b9ff0 100%);
            color: white;
            border-radius: 18px 18px 0 0;
            padding: 20px 25px;
            border: none;
        }

        #profileSetupModal .modal-title {
            font-weight: 700;
            font-size: 18px;
        }

        #profileSetupModal .modal-body {
            padding: 30px 25px;
        }

        #profileSetupModal .form-label {
            font-weight: 600;
            color: #1f2a44;
            margin-bottom: 8px;
            font-size: 14px;
        }

        #profileSetupModal .form-select {
            border-radius: 10px;
            border: 1px solid #e6edf7;
            padding: 12px 16px;
            font-size: 14px;
        }

        #profileSetupModal .form-select:focus {
            border-color: #5a8dee;
            box-shadow: 0 0 0 3px rgba(90, 141, 238, 0.1);
        }

        #profileSetupModal .btn-primary {
            background: linear-gradient(135deg, #5a8dee 0%, #7b9ff0 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
        }

        #profileSetupModal .btn-primary:hover {
            background: linear-gradient(135deg, #4a7dde 0%, #6b8fe0 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90, 141, 238, 0.3);
        }

        .select2-container--default .select2-selection--multiple {
            border-radius: 10px !important;
            border: 1px solid #e6edf7 !important;
            min-height: 45px !important;
            padding: 4px 8px !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: #5a8dee !important;
            box-shadow: 0 0 0 3px rgba(90, 141, 238, 0.1) !important;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #5a8dee !important;
            border: none !important;
            border-radius: 8px !important;

            padding: 6px 12px !important;
            padding-left: 14px !important;

            color: #fff !important;
            display: inline-flex !important;
            /* 👈 key */
            align-items: center !important;
            gap: 12px !important;
            /* space between ❌ and text */
            width: auto !important;
            /* 👈 fit content */
            min-width: unset !important;
            /* remove forced width */
            max-width: 100% !important;
        }


        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff !important;
            margin-right: 12px !important;
            /* 👈 space from text */
            font-size: 10px !important;
            cursor: pointer !important;
            line-height: 1 !important;
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
            <button class="menu-toggle">
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
                                <img src="<?= htmlspecialchars($doctor['profile_image']) ?>" alt="Profile" class="profile-image">
                            <?php else: ?>
                                <div class="profile-placeholder">
                                    <?= strtoupper(substr($doctor['firstname'], 0, 1)) ?>
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
            <a href="doctor_dashboard.php" class="nav-item active">
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
            <a href="doctor_billing.php" class="nav-item">
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
        <!-- Breadcrumb -->
        <div class="breadcrumb-bar">
            <div class="breadcrumb-title">
                <span>Doctor Dashboard</span>
                <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
                <i class="fas fa-home"></i>
            </div>
            <div class="breadcrumb-links">
                <span style="color: #5a8dee; font-weight: 600;">Dashboard</span>
            </div>
        </div>

        <?php if (isset($_SESSION['doctor_photo_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['doctor_photo_message_type'] ?? 'info') ?> alert-dismissible fade show mx-3" role="alert">
                <?= htmlspecialchars($_SESSION['doctor_photo_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['doctor_photo_message'], $_SESSION['doctor_photo_message_type']); ?>
        <?php endif; ?>

        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <div class="welcome-text">Welcome back</div>
                    <div class="doctor-name-large">DR. <?= htmlspecialchars(strtoupper($doctor['firstname'] . ' ' . $doctor['lastname'])) ?>!</div>
                    <div class="speciality"><?= htmlspecialchars($doctor['specialities'] ?: 'Gynecologist, MBBS,MD') ?></div>

                    <!-- Stat Boxes -->
                    <div class="stat-boxes">
                        <div class="stat-box purple">
                            <div class="stat-box-label">Appointments</div>
                            <div class="stat-box-value"><?= $todayAppointments ?>+</div>
                        </div>
                        <div class="stat-box pink">
                            <div class="stat-box-label">Surgeries</div>
                            <div class="stat-box-value">3+</div>
                        </div>
                        <div class="stat-box green">
                            <div class="stat-box-label">Room Visit</div>
                            <div class="stat-box-value"><?= $visitedAppointments ?>+</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <img src="Screenshot_2026-02-18_123531-removebg-preview.png" alt="Illustration" class="doctor-illustration" style="width: 175px;margin-left: 110px;height: 175px;margin-top: 55px;">
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-wrapper">
            <!-- Left Column - Main Dashboard Cards -->
            <div>
                <div class="row g-4">
                    <!-- Appointments Summary Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">Appointments</h6>
                                <i class="fas fa-clock" style="color: #f59e0b; font-size: 20px;"></i>
                            </div>
                            <div class="text-muted" style="font-size: 13px; margin-bottom: 15px;">Today's Summary</div>

                            <div class="text-center mb-4">
                                <!-- Simple Donut Chart Placeholder -->
                                <svg width="160" height="160" viewBox="0 0 160 160">
                                    <circle cx="80" cy="80" r="60" fill="none" stroke="#e5e7eb" stroke-width="20"></circle>
                                    <circle cx="80" cy="80" r="60" fill="none" stroke="#3b82f6" stroke-width="20"
                                        stroke-dasharray="100 377" stroke-dashoffset="0" transform="rotate(-90 80 80)"></circle>
                                    <circle cx="80" cy="80" r="60" fill="none" stroke="#10b981" stroke-width="20"
                                        stroke-dasharray="90 377" stroke-dashoffset="-100" transform="rotate(-90 80 80)"></circle>
                                    <circle cx="80" cy="80" r="60" fill="none" stroke="#ef4444" stroke-width="20"
                                        stroke-dasharray="30 377" stroke-dashoffset="-190" transform="rotate(-90 80 80)"></circle>
                                </svg>
                                <div style="margin-top: -100px; font-size: 24px; font-weight: 700; color: #333;">
                                    <?= $todayAppointments + $pendingAppointments + $visitedAppointments ?>
                                </div>
                                <div style="font-size: 13px; color: #999;">Total</div>
                            </div>

                            <div style="font-size: 13px;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle" style="color: #3b82f6; font-size: 8px;"></i> Scheduled: 28</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle" style="color: #10b981; font-size: 8px;"></i> Completed: 24</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-circle" style="color: #ef4444; font-size: 8px;"></i> Cancelled: 4</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">Performance</h6>
                                <i class="fas fa-chart-line" style="color: #10b981; font-size: 20px;"></i>
                            </div>
                            <div class="text-muted" style="font-size: 13px; margin-bottom: 15px;">Daily metrics</div>

                            <!-- Bar Chart Placeholder -->
                            <div class="text-center mb-3">
                                <svg width="100%" height="140" viewBox="0 0 280 140">
                                    <rect x="20" y="60" width="30" height="80" fill="#10b981" opacity="0.7" rx="4"></rect>
                                    <rect x="60" y="40" width="30" height="100" fill="#10b981" opacity="0.7" rx="4"></rect>
                                    <rect x="100" y="70" width="30" height="70" fill="#10b981" opacity="0.7" rx="4"></rect>
                                    <rect x="140" y="30" width="30" height="110" fill="#10b981" opacity="0.7" rx="4"></rect>
                                    <rect x="180" y="50" width="30" height="90" fill="#10b981" opacity="0.7" rx="4"></rect>
                                    <rect x="220" y="45" width="30" height="95" fill="#10b981" opacity="0.7" rx="4"></rect>
                                </svg>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-center flex-fill">
                                    <div style="color: #10b981; font-size: 20px; font-weight: 700;">18 min</div>
                                    <div style="font-size: 12px; color: #999;">Avg. Consultation</div>
                                </div>
                                <div class="text-center flex-fill">
                                    <div style="color: #10b981; font-size: 20px; font-weight: 700;">24</div>
                                    <div style="font-size: 12px; color: #999;">Patients/Day</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Revenue Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">Today's Revenue</h6>
                                <i class="fas fa-dollar-sign" style="color: #3b82f6; font-size: 20px;"></i>
                            </div>
                            <div style="font-size: 28px; font-weight: 700; color: #3b82f6; margin-bottom: 15px;">$4,250</div>

                            <!-- Area Chart Placeholder -->
                            <svg width="100%" height="120" viewBox="0 0 280 120">
                                <defs>
                                    <linearGradient id="grad1" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:#a78bfa;stop-opacity:0.3" />
                                        <stop offset="100%" style="stop-color:#a78bfa;stop-opacity:0.05" />
                                    </linearGradient>
                                    <linearGradient id="grad2" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:#3b82f6;stop-opacity:0.3" />
                                        <stop offset="100%" style="stop-color:#3b82f6;stop-opacity:0.05" />
                                    </linearGradient>
                                    <linearGradient id="grad3" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" style="stop-color:#10b981;stop-opacity:0.3" />
                                        <stop offset="100%" style="stop-color:#10b981;stop-opacity:0.05" />
                                    </linearGradient>
                                </defs>
                                <path d="M 0,80 Q 70,60 140,70 T 280,50 L 280,120 L 0,120 Z" fill="url(#grad1)"></path>
                                <path d="M 0,90 Q 70,75 140,80 T 280,65 L 280,120 L 0,120 Z" fill="url(#grad2)"></path>
                                <path d="M 0,100 Q 70,90 140,95 T 280,85 L 280,120 L 0,120 Z" fill="url(#grad3)"></path>
                            </svg>

                            <div style="font-size: 11px; margin-top: 10px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><i class="fas fa-circle" style="color: #10b981; font-size: 6px;"></i> Walk-ins: $1,850</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><i class="fas fa-circle" style="color: #3b82f6; font-size: 6px;"></i> Follow-ups: $1,200</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-circle" style="color: #a78bfa; font-size: 6px;"></i> Online: $1,200</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Profile Card -->
            <div class="right-profile-card">
                <div class="card text-center">
                    <?php if (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image'])): ?>
                        <img src="<?= htmlspecialchars($doctor['profile_image']) ?>" alt="Profile" class="profile-card-image" id="profileCardImage">
                    <?php else: ?>
                        <div class="profile-card-placeholder" id="profileCardImage">
                            <?= strtoupper(substr($doctor['firstname'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-card-name">Dr. <?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></div>
                    <div class="profile-card-spec"><?= htmlspecialchars($doctor['specialities'] ?: 'Orthopedics') ?> - Restar Hospital</div>

                    <!-- Patients Stats -->
                    <div class="profile-stats-box">
                        <div class="profile-stat-row">
                            <div>
                                <div class="profile-stat-value" style="color: #333;">3,897</div>
                                <div class="profile-stat-label">Patients</div>
                            </div>
                            <div>
                                <svg viewBox="0 0 36 36" style="width: 80px; height: 80px;">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#e5e7eb" stroke-width="3"></circle>
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#ff6b6b" stroke-width="3" stroke-dasharray="60, 100" stroke-linecap="round" transform="rotate(-90 18 18)"></circle>
                                </svg>
                                <div class="percentage-text" style="margin-top: -60px;">60%</div>
                            </div>
                        </div>
                        <div class="text-muted" style="font-size: 12px; margin-top: 10px;">8,000 Patients Limit</div>
                    </div>

                    <!-- Surgery & Consultation -->
                    <div class="profile-stat-row" style="margin-top: 20px;">
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Surgery</div>
                            <div class="profile-stat-value">578</div>
                        </div>
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Consultation</div>
                            <div class="profile-stat-value">387</div>
                        </div>
                    </div>

                    <!-- Patients & Appointment -->
                    <div class="profile-stat-row" style="margin-top: 15px;">
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Patients</div>
                            <div class="profile-stat-value">4,257</div>
                        </div>
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Appointment</div>
                            <div class="profile-stat-value">1,243</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Setup Modal -->
    <div class="modal fade" id="profileSetupModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-md"></i> Complete Your Profile</h5>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-4">Please select your category and specialties to complete your profile setup.</p>
                    <form id="profileSetupForm">
                        <div class="mb-3">
                            <label for="categorySelect" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="categorySelect" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="specialitySelect" class="form-label">Specialties <span class="text-danger">*</span></label>
                            <select class="form-select" id="specialitySelect" multiple="multiple" required style="width: 100%;">
                                <option value="" disabled>Select category first</option>
                            </select>
                            <small class="text-muted">You can select multiple specialties</small>
                        </div>
                        <div class="mb-3">
                            <label for="clinicNameInput" class="form-label">Clinic/Hospital Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="clinicNameInput" required placeholder="e.g. Apollo Hospital">
                        </div>
                        <div class="mb-3">
                            <label for="experienceInput" class="form-label">Experience (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="experienceInput" required min="0" placeholder="e.g. 5">
                        </div>
                        <div class="mb-3">
                            <label for="educationInput" class="form-label">Education/Degrees <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="educationInput" required placeholder="e.g. MBBS, MD(Cardiology)">
                        </div>
                        <div class="mb-3">
                            <label for="bioInput" class="form-label">Professional Bio <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="bioInput" rows="3" required placeholder="Tell patients about your expertise..."></textarea>
                        </div>
                        <div id="profileSetupMessage"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="saveProfileBtn">
                        <i class="fas fa-save"></i> Save Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Show profile setup modal if needed
        <?php if ($needsProfileSetup): ?>
            $(document).ready(function() {
                $('#profileSetupModal').modal('show');
            });
        <?php endif; ?>

        // Initialize Select2 for multiple selection
        $(document).ready(function() {
            $('#specialitySelect').select2({
                placeholder: 'Select specialties',
                allowClear: true,
                dropdownParent: $('#profileSetupModal')
            });
        });

        // Fetch specialties when category changes
        $('#categorySelect').on('change', function() {
            const categoryId = $(this).val();
            if (!categoryId) {
                $('#specialitySelect').html('<option value="" disabled>Select category first</option>');
                return;
            }

            $.ajax({
                url: 'get_specialities.php',
                type: 'GET',
                data: {
                    category_id: categoryId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '';
                        response.specialities.forEach(function(spec) {
                            options += `<option value="${spec.id}">${spec.doctor_speciality}</option>`;
                        });
                        $('#specialitySelect').html(options).trigger('change');
                    }
                },
                error: function() {
                    alert('Failed to load specialties');
                }
            });
        });

        // Save profile setup
        $('#saveProfileBtn').on('click', function() {
            const categoryId = $('#categorySelect').val();
            const specialities = $('#specialitySelect').val();
            const clinicName = $('#clinicNameInput').val();
            const experience = $('#experienceInput').val();
            const education = $('#educationInput').val();
            const bio = $('#bioInput').val();

            if (!categoryId || !specialities || specialities.length === 0 || !clinicName || !experience || !education || !bio) {
                $('#profileSetupMessage').html('<div class="alert alert-danger">Please fill out all required fields</div>');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'save_doctor_profile.php',
                type: 'POST',
                data: {
                    category_id: categoryId,
                    specialities: specialities,
                    clinic_name: clinicName,
                    experience_years: experience,
                    education: education,
                    bio: bio
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#profileSetupMessage').html('<div class="alert alert-success">Profile updated successfully! Reloading...</div>');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        $('#profileSetupMessage').html('<div class="alert alert-danger">' + response.message + '</div>');
                        $('#saveProfileBtn').prop('disabled', false).html('<i class="fas fa-save"></i> Save Profile');
                    }
                },
                error: function() {
                    $('#profileSetupMessage').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                    $('#saveProfileBtn').prop('disabled', false).html('<i class="fas fa-save"></i> Save Profile');
                }
            });
        });

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