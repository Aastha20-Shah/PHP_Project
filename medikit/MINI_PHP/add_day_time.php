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
$msg = "";
$msg_type = "";

$doctor_id = (int)$doctor_id;

$notifications = medikit_doctor_unseen_notifications_list($conn, $doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, $doctor_id);

// Doctor info (for dashboard header/sidebar)
$doctor_res = mysqli_query($conn, "SELECT firstname, lastname, profile_image FROM users WHERE id = $doctor_id LIMIT 1");
$doctor = $doctor_res ? mysqli_fetch_assoc($doctor_res) : null;
if (!$doctor) {
    $doctor = ['firstname' => 'Doctor', 'lastname' => '', 'profile_image' => ''];
}

$doctor['profile_image'] = $doctor['profile_image'] ?? '';

// Days map
$days_map = [
    '1' => 'Monday',
    '2' => 'Tuesday',
    '3' => 'Wednesday',
    '4' => 'Thursday',
    '5' => 'Friday',
    '6' => 'Saturday',
    '7' => 'Sunday'
];

// --------------------------
// Add New Time Slot
// --------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_time'])) {
    $days = $_POST['days'] ?? [];
    $start_time_in = trim((string)($_POST['start_time'] ?? ''));
    $end_time_in = trim((string)($_POST['end_time'] ?? ''));

    if (empty($days) || $start_time_in === '' || $end_time_in === '') {
        $msg = "Please select at least one day and enter start/end time.";
        $msg_type = "error";
    } else {
        $start_ts = strtotime('1970-01-01 ' . $start_time_in);
        $end_ts = strtotime('1970-01-01 ' . $end_time_in);

        if ($start_ts === false || $end_ts === false || $end_ts <= $start_ts) {
            $msg = "End time must be after start time.";
            $msg_type = "error";
        } else {
            $start_time = mysqli_real_escape_string($conn, $start_time_in);
            $end_time = mysqli_real_escape_string($conn, $end_time_in);

            $check_q = mysqli_query($conn, "
                SELECT COUNT(t.id)
                FROM doctor_available_time t
                JOIN doctor_available_day d ON t.day_id = d.id
                WHERE d.doctor_id = $doctor_id AND t.start_time = '$start_time' AND t.end_time = '$end_time'
            ");
            $count = $check_q ? (int)(mysqli_fetch_row($check_q)[0] ?? 0) : 0;

            if ($count > 0) {
                $msg = "This exact time range already exists. Use the table to add/remove days.";
                $msg_type = "error";
            } else {
                foreach ($days as $day_num) {
                    $day_num = (int)$day_num;
                    if ($day_num < 1 || $day_num > 7) {
                        continue;
                    }

                    $day_q = mysqli_query($conn, "SELECT id FROM doctor_available_day WHERE doctor_id = $doctor_id AND day = $day_num LIMIT 1");
                    if ($day_q && mysqli_num_rows($day_q) > 0) {
                        $day_id = (int)(mysqli_fetch_assoc($day_q)['id'] ?? 0);
                    } else {
                        mysqli_query($conn, "INSERT INTO doctor_available_day (doctor_id, day) VALUES ($doctor_id, $day_num)");
                        $day_id = (int)mysqli_insert_id($conn);
                    }

                    if ($day_id > 0) {
                        $exist_check_q = mysqli_query($conn, "SELECT id FROM doctor_available_time WHERE day_id = $day_id AND start_time = '$start_time' AND end_time = '$end_time' LIMIT 1");
                        if (!$exist_check_q || mysqli_num_rows($exist_check_q) == 0) {
                            mysqli_query($conn, "INSERT INTO doctor_available_time (day_id, start_time, end_time) VALUES ($day_id, '$start_time', '$end_time')");
                        }
                    }
                }
                $msg = "New time slot saved successfully!";
                $msg_type = "success";
            }
        }
    }
}

// --------------------------
// DELETE Time Slot (Handle Delete button click)
// --------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_time_id'])) {
    $time_id = intval($_POST['time_id']);

    // Find the time range associated with this time_id
    $time_q = mysqli_query($conn, "
        SELECT t.start_time, t.end_time
        FROM doctor_available_time t
        JOIN doctor_available_day d ON t.day_id = d.id
        WHERE t.id = $time_id AND d.doctor_id = $doctor_id
        LIMIT 1
    ");
    if ($time_row = mysqli_fetch_assoc($time_q)) {
        $del_start = mysqli_real_escape_string($conn, $time_row['start_time']);
        $del_end = mysqli_real_escape_string($conn, $time_row['end_time']);

        // Find all day_ids that use this time range for this doctor
        $day_ids_to_check = [];
        $day_q = mysqli_query($conn, "
            SELECT d.id 
            FROM doctor_available_day d
            JOIN doctor_available_time t ON d.id = t.day_id
            WHERE d.doctor_id = '$doctor_id' AND t.start_time = '$del_start' AND t.end_time = '$del_end'
        ");
        while ($d_row = mysqli_fetch_assoc($day_q)) {
            $day_ids_to_check[] = $d_row['id'];
        }

        // Delete all corresponding time slot entries
        mysqli_query($conn, "
            DELETE t 
            FROM doctor_available_time t
            JOIN doctor_available_day d ON t.day_id = d.id
            WHERE d.doctor_id = '$doctor_id' AND t.start_time = '$del_start' AND t.end_time = '$del_end'
        ");

        // Cleanup orphaned doctor_available_day entries
        foreach ($day_ids_to_check as $did) {
            $check_time_q = mysqli_query($conn, "SELECT COUNT(*) FROM doctor_available_time WHERE day_id='$did'");
            $count_t = mysqli_fetch_row($check_time_q)[0];
            if ($count_t == 0) {
                mysqli_query($conn, "DELETE FROM doctor_available_day WHERE id='$did'");
            }
        }

        $msg = "Time slot deleted successfully.";
        $msg_type = "success";
    } else {
        $msg = "Invalid time slot.";
        $msg_type = "error";
    }
}


// --------------------------
// Fetch Time Slots
// --------------------------
$time_slots = [];
$res = mysqli_query($conn, "
    SELECT t.id as time_id, t.start_time, t.end_time, d.id as day_id, d.day
    FROM doctor_available_time t
    JOIN doctor_available_day d ON t.day_id=d.id
    WHERE d.doctor_id = $doctor_id
    ORDER BY t.start_time, t.end_time
");

while ($row = mysqli_fetch_assoc($res)) {
    $unique_time_key = $row['start_time'] . '_' . $row['end_time'];
    if (!isset($time_slots[$unique_time_key])) {
        $time_slots[$unique_time_key] = [
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'days' => []
        ];
    }
    $time_slots[$unique_time_key]['days'][$row['day']] = [
        'day_id' => $row['day_id'],
        'time_id' => $row['time_id']
    ];
}

$time_slots = array_values($time_slots);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - Doctor Panel</title>

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
            box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.05);
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
            gap: 18px;
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

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: default;
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
            font-size: 13px;
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

        .panel-card {
            background: #ffffff;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 12px 28px rgba(31, 42, 68, 0.08);
            border: 1px solid #e6edf7;
            position: relative;
            z-index: 1;
        }

        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2a44;
            margin-bottom: 16px;
        }

        .availability-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
            font-size: 13px;
        }

        .availability-table th {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #7a879a;
            background: #f7f9fd;
            border-bottom: 1px solid #e6edf7;
            padding: 12px 14px;
            white-space: nowrap;
        }

        .availability-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f7;
            color: #1f2a44;
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .availability-table tbody tr:hover {
            background: #fbfcff;
        }

        .day-col {
            text-align: center;
            font-size: 13px;
        }

        .days-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            font-size: 13px;
        }

        .days-grid .form-check {
            margin: 0;
            font-size: 13px;
        }
    </style>

    <script>
        async function toggleDay(checkbox, dayNum) {
            const row = checkbox.closest('tr');
            const startTime = row?.dataset?.startTime;
            const endTime = row?.dataset?.endTime;
            const action = checkbox.checked ? 'add' : 'delete';

            if (!startTime || !endTime) {
                alert('Missing time range.');
                checkbox.checked = !checkbox.checked;
                return;
            }

            const body = new URLSearchParams({
                day_num: String(dayNum),
                start_time: startTime,
                end_time: endTime,
                action
            });

            try {
                const resp = await fetch('update_day.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body
                });

                const data = await resp.json().catch(() => null);
                if (resp.ok && data && data.status === 'success') {
                    window.location.reload();
                    return;
                }

                alert((data && data.message) ? data.message : 'Could not update availability.');
            } catch (e) {
                alert('Network error. Please try again.');
            }

            checkbox.checked = !checkbox.checked;
        }
    </script>
</head>

<body>

    <!-- TOP HEADER -->
    <div class="top-header">
        <div class="header-left">
            <div class="logo">
                <i class="fa-solid fa-stethoscope"></i>
                <span>Medikit</span>
            </div>
            <button class="menu-toggle" type="button">
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
                <span class="user-name"><?= htmlspecialchars(trim($doctor['firstname'] . ' ' . $doctor['lastname'])) ?></span>
                <?php
                $doctor_avatar_src = (!empty($doctor['profile_image']) && file_exists(__DIR__ . '/' . $doctor['profile_image']))
                    ? $doctor['profile_image']
                    : "https://ui-avatars.com/api/?name=" . urlencode(trim($doctor['firstname'] . ' ' . $doctor['lastname'])) . "&background=5a8dee&color=fff";
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
                                <?= strtoupper(substr($doctor['firstname'] ?: 'D', 0, 1)) ?>
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
            <div class="profile-name"><?= htmlspecialchars(trim($doctor['firstname'] . ' ' . $doctor['lastname'])) ?></div>
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
            <a href="add_day_time.php" class="nav-item active">
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
        <div class="breadcrumb-bar">
            <div class="breadcrumb-title">
                <span>Manage Availability</span>
                <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
                <i class="fas fa-clock"></i>
            </div>
            <div class="breadcrumb-links">
                <a href="doctor_dashboard.php" style="color: #5a8dee; font-weight: 600; text-decoration: none;">Dashboard</a>
                <span>/</span>
                <span style="color: #5a8dee; font-weight: 600;">Day &amp; Time</span>
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
            <div class="card-title">Availability (Day &amp; Time)</div>

            <?php if ($msg !== ""): ?>
                <div class="alert <?= ($msg_type === 'success') ? 'alert-success' : 'alert-danger' ?> mb-4">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($time_slots)): ?>
                <div class="table-responsive">
                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th>Start</th>
                                <th>End</th>
                                <?php foreach ($days_map as $day): ?>
                                    <th class="day-col"><?= htmlspecialchars($day) ?></th>
                                <?php endforeach; ?>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $slot): ?>
                                <?php
                                $any_day_key = array_key_first($slot['days']);
                                $any_time_id = $any_day_key !== null ? (int)$slot['days'][$any_day_key]['time_id'] : 0;
                                ?>
                                <tr data-start-time="<?= htmlspecialchars($slot['start_time']) ?>" data-end-time="<?= htmlspecialchars($slot['end_time']) ?>">
                                    <td><?= htmlspecialchars(date("g:i A", strtotime($slot['start_time']))) ?></td>
                                    <td><?= htmlspecialchars(date("g:i A", strtotime($slot['end_time']))) ?></td>
                                    <?php foreach ($days_map as $num => $day_name):
                                        $day_data = $slot['days'][$num] ?? null;
                                        $checked = $day_data ? 'checked' : '';
                                    ?>
                                        <td class="day-col">
                                            <input class="form-check-input" type="checkbox" onchange="toggleDay(this, <?= (int)$num ?>)" <?= $checked ?>>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <?php if ($any_time_id > 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this time range for all days?');">
                                                <input type="hidden" name="delete_time_id" value="1">
                                                <input type="hidden" name="time_id" value="<?= $any_time_id ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4">No time slots added yet.</div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="card-title">Add New Time Slot</div>
            <form method="POST">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold d-block">Days</label>
                        <div class="days-grid">
                            <?php foreach ($days_map as $num => $dname): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="days[]" id="day_<?= (int)$num ?>" value="<?= (int)$num ?>">
                                    <label class="form-check-label" for="day_<?= (int)$num ?>"><?= htmlspecialchars($dname) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" name="add_time" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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