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


$doctor_id = (int)$_SESSION['doctor_id'];

$notifications = medikit_doctor_unseen_notifications_list($conn, $doctor_id, 5);
$notification_count = medikit_doctor_unseen_notifications_count($conn, $doctor_id);

function medikit_fetch_row(mysqli $conn, string $sql, string $types = '', ...$params): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? ($res->fetch_assoc() ?: []) : [];
    $stmt->close();

    return is_array($row) ? $row : [];
}

function medikit_fetch_scalar(mysqli $conn, string $sql, string $types = '', ...$params)
{
    $row = medikit_fetch_row($conn, $sql, $types, ...$params);
    if (empty($row)) {
        return 0;
    }

    $value = array_values($row)[0] ?? 0;
    return is_numeric($value) ? ($value + 0) : 0;
}

/* =======================
   DASHBOARD METRICS
======================= */

$currency_symbol = '₹';

// Total Patients (distinct) who registered/booked an appointment (ignore rejected)
$totalPatients = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(DISTINCT patient_id) AS total
       FROM visit_booking
      WHERE doctor_id = ?
        AND patient_id <> 0
        AND status IN ('pending','accepted','visited')",
    'i',
    $doctor_id
);

// Patients with booked appointments not yet completed (distinct)
$incompletePatients = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(DISTINCT patient_id) AS total
       FROM visit_booking
      WHERE doctor_id = ?
        AND patient_id <> 0
        AND status IN ('pending','accepted')",
    'i',
    $doctor_id
);

// Total Appointments
$totalAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ?",
    'i',
    $doctor_id
);

// Status counts (all-time)
$pendingAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ? AND status = 'pending'",
    'i',
    $doctor_id
);

$acceptedAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ? AND status = 'accepted'",
    'i',
    $doctor_id
);

$visitedAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ? AND status = 'visited'",
    'i',
    $doctor_id
);

$rejectedAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ? AND status = 'rejected'",
    'i',
    $doctor_id
);

// Upcoming scheduled appointments
$upcomingAppointments = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total
       FROM visit_booking
      WHERE doctor_id = ?
        AND appointment_date >= CURDATE()
        AND status IN ('pending','accepted')",
    'i',
    $doctor_id
);

// Today's status breakdown
$today_status = [
    'pending' => 0,
    'accepted' => 0,
    'visited' => 0,
    'rejected' => 0,
];

$today_stmt = $conn->prepare(
    "SELECT status, COUNT(*) AS total
       FROM visit_booking
      WHERE doctor_id = ?
        AND appointment_date = CURDATE()
      GROUP BY status"
);
if ($today_stmt) {
    $today_stmt->bind_param('i', $doctor_id);
    $today_stmt->execute();
    $today_res = $today_stmt->get_result();
    while ($row = $today_res->fetch_assoc()) {
        $s = (string)($row['status'] ?? '');
        if ($s !== '' && array_key_exists($s, $today_status)) {
            $today_status[$s] = (int)($row['total'] ?? 0);
        }
    }
    $today_stmt->close();
}

$todayScheduled = $today_status['pending'] + $today_status['accepted'];
$todayCompleted = $today_status['visited'];
$todayCancelled = $today_status['rejected'];
$todayAppointments = $todayScheduled + $todayCompleted + $todayCancelled;

// Today's appointments list (for dashboard feed)
$todayAppointmentsList = [];
$today_list_stmt = $conn->prepare(
    "SELECT vb.id AS booking_id,
            vb.status,
            p.firstname AS patient_firstname,
            p.lastname AS patient_lastname,
            dat.start_time,
            dat.end_time
       FROM visit_booking vb
       LEFT JOIN patient p ON vb.patient_id = p.id
       LEFT JOIN doctor_available_time dat ON vb.time_id = dat.id
      WHERE vb.doctor_id = ?
        AND vb.patient_id <> 0
        AND vb.appointment_date = CURDATE()
      ORDER BY (dat.start_time IS NULL) ASC, dat.start_time ASC, vb.id ASC
      LIMIT 10"
);
if ($today_list_stmt) {
    $today_list_stmt->bind_param('i', $doctor_id);
    $today_list_stmt->execute();
    $today_list_res = $today_list_stmt->get_result();
    if ($today_list_res) {
        while ($r = $today_list_res->fetch_assoc()) {
            $todayAppointmentsList[] = $r;
        }
    }
    $today_list_stmt->close();
}

// Prescriptions
$prescriptionsCount = (int)medikit_fetch_scalar(
    $conn,
    "SELECT COUNT(*) AS total FROM clinic_prescriptions WHERE doctor_id = ?",
    'i',
    $doctor_id
);

// Revenue
$todayRevenue = (float)medikit_fetch_scalar(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
       FROM clinic_bills
      WHERE doctor_id = ?
        AND payment_status = 'paid'
        AND DATE(created_at) = CURDATE()",
    'i',
    $doctor_id
);

// Revenue breakdown by payment method (top 2 + other)
$payment_methods = [];
$pay_stmt = $conn->prepare(
    "SELECT payment_method, COALESCE(SUM(amount), 0) AS total
       FROM clinic_bills
      WHERE doctor_id = ?
        AND payment_status = 'paid'
        AND DATE(created_at) = CURDATE()
      GROUP BY payment_method
      ORDER BY total DESC"
);
if ($pay_stmt) {
    $pay_stmt->bind_param('i', $doctor_id);
    $pay_stmt->execute();
    $pay_res = $pay_stmt->get_result();
    while ($row = $pay_res->fetch_assoc()) {
        $payment_methods[] = [
            'method' => (string)($row['payment_method'] ?? ''),
            'total' => (float)($row['total'] ?? 0),
        ];
    }
    $pay_stmt->close();
}

$rev_line_1 = $payment_methods[0] ?? ['method' => 'Cash', 'total' => 0];
$rev_line_2 = $payment_methods[1] ?? ['method' => 'UPI', 'total' => 0];
$rev_other_total = 0.0;
for ($i = 2; $i < count($payment_methods); $i++) {
    $rev_other_total += (float)($payment_methods[$i]['total'] ?? 0);
}
$rev_line_3 = ['method' => 'Other', 'total' => $rev_other_total];

// Last 7 days performance (appointments + unique patients)
$perf_map = [];
$perf_pat_map = [];
$perf_stmt = $conn->prepare(
    "SELECT appointment_date,
            COUNT(*) AS total,
            COUNT(DISTINCT patient_id) AS patients
       FROM visit_booking
      WHERE doctor_id = ?
        AND appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
        AND status IN ('pending','accepted','visited')
      GROUP BY appointment_date
      ORDER BY appointment_date"
);
if ($perf_stmt) {
    $perf_stmt->bind_param('i', $doctor_id);
    $perf_stmt->execute();
    $perf_res = $perf_stmt->get_result();
    while ($row = $perf_res->fetch_assoc()) {
        $d = (string)($row['appointment_date'] ?? '');
        if ($d !== '') {
            $perf_map[$d] = (int)($row['total'] ?? 0);
            $perf_pat_map[$d] = (int)($row['patients'] ?? 0);
        }
    }
    $perf_stmt->close();
}

$performanceLabels = [];
$performanceCounts = [];
$performancePatients = [];
$sumAppointments = 0;
$sumPatients = 0;

$dt = new DateTimeImmutable('today -6 days');
for ($i = 0; $i < 7; $i++) {
    $d = $dt->format('Y-m-d');
    $performanceLabels[] = $dt->format('D');
    $count = (int)($perf_map[$d] ?? 0);
    $pcount = (int)($perf_pat_map[$d] ?? 0);
    $performanceCounts[] = $count;
    $performancePatients[] = $pcount;
    $sumAppointments += $count;
    $sumPatients += $pcount;
    $dt = $dt->modify('+1 day');
}

$avgAppointmentsPerDay = (int)round($sumAppointments / 7);
$avgPatientsPerDay = (int)round($sumPatients / 7);

// Last 7 days revenue
$rev_map = [];
$rev_stmt = $conn->prepare(
    "SELECT DATE(created_at) AS day,
            COALESCE(SUM(amount), 0) AS total
       FROM clinic_bills
      WHERE doctor_id = ?
        AND payment_status = 'paid'
        AND DATE(created_at) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
      GROUP BY DATE(created_at)
      ORDER BY day"
);
if ($rev_stmt) {
    $rev_stmt->bind_param('i', $doctor_id);
    $rev_stmt->execute();
    $rev_res = $rev_stmt->get_result();
    while ($row = $rev_res->fetch_assoc()) {
        $d = (string)($row['day'] ?? '');
        if ($d !== '') {
            $rev_map[$d] = (float)($row['total'] ?? 0);
        }
    }
    $rev_stmt->close();
}

$revenueLabels = [];
$revenueTotals = [];
$dt = new DateTimeImmutable('today -6 days');
for ($i = 0; $i < 7; $i++) {
    $d = $dt->format('Y-m-d');
    $revenueLabels[] = $dt->format('D');
    $revenueTotals[] = (float)($rev_map[$d] ?? 0);
    $dt = $dt->modify('+1 day');
}

// Patients not yet completed percentage (no patient limit)
$patientPercent = 0;
if ($totalPatients > 0) {
    $patientPercent = (int)min(100, round(($incompletePatients / $totalPatients) * 100));
}

// Doctor Info
$doctor = medikit_fetch_row(
    $conn,
    "SELECT u.firstname, u.lastname, u.profile_image, u.email, u.category_id, u.clinic_name, u.education,
            GROUP_CONCAT(s.doctor_speciality SEPARATOR ', ') AS specialities,
            COUNT(ds.id) as speciality_count
       FROM users u
       LEFT JOIN doctor_speciality ds ON ds.doctor_id = u.id
       LEFT JOIN speciality s ON s.id = ds.speciality_id
      WHERE u.id = ?
      GROUP BY u.id",
    'i',
    $doctor_id
);

if (!is_array($doctor) || empty($doctor)) {
    $doctor = [
        'firstname' => 'Doctor',
        'lastname' => '',
        'profile_image' => '',
        'email' => '',
        'category_id' => null,
        'clinic_name' => '',
        'education' => '',
        'specialities' => '',
        'speciality_count' => 0,
    ];
}

$doctor['profile_image'] = (string)($doctor['profile_image'] ?? '');

$doctor_education_label = trim((string)($doctor['education'] ?? ''));
$doctor_speciality_label = $doctor_education_label !== ''
    ? $doctor_education_label
    : 'MBBS, MD';

$doctor_clinic_label = trim((string)($doctor['clinic_name'] ?? ''));

// Check if profile setup is needed
$needsProfileSetup = empty($doctor['category_id']) || (int)($doctor['speciality_count'] ?? 0) === 0;

/* =======================
   CALENDAR (month widget)
======================= */

$cal_month = isset($_GET['cal_month']) ? (int)$_GET['cal_month'] : (int)date('n');
$cal_year = isset($_GET['cal_year']) ? (int)$_GET['cal_year'] : (int)date('Y');

if ($cal_month < 1 || $cal_month > 12) {
    $cal_month = (int)date('n');
}
if ($cal_year < 2000 || $cal_year > 2100) {
    $cal_year = (int)date('Y');
}

$cal_first = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$cal_last = date('Y-m-t', strtotime($cal_first));

$calendarCounts = [];
$cal_stmt = $conn->prepare(
    "SELECT appointment_date, status, COUNT(*) AS total
       FROM visit_booking
      WHERE doctor_id = ?
        AND appointment_date BETWEEN ? AND ?
      GROUP BY appointment_date, status"
);
if ($cal_stmt) {
    $cal_stmt->bind_param('iss', $doctor_id, $cal_first, $cal_last);
    $cal_stmt->execute();
    $cal_res = $cal_stmt->get_result();
    while ($row = $cal_res->fetch_assoc()) {
        $d = (string)($row['appointment_date'] ?? '');
        $s = (string)($row['status'] ?? '');
        if ($d !== '' && $s !== '') {
            if (!isset($calendarCounts[$d])) {
                $calendarCounts[$d] = [];
            }
            $calendarCounts[$d][$s] = (int)($row['total'] ?? 0);
        }
    }
    $cal_stmt->close();
}

$cal_prev_month = $cal_month - 1;
$cal_prev_year = $cal_year;
if ($cal_prev_month < 1) {
    $cal_prev_month = 12;
    $cal_prev_year--;
}

$cal_next_month = $cal_month + 1;
$cal_next_year = $cal_year;
if ($cal_next_month > 12) {
    $cal_next_month = 1;
    $cal_next_year++;
}

$qs_prev = $_GET;
$qs_prev['cal_month'] = $cal_prev_month;
$qs_prev['cal_year'] = $cal_prev_year;

$qs_next = $_GET;
$qs_next['cal_month'] = $cal_next_month;
$qs_next['cal_year'] = $cal_next_year;

$cal_prev_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($qs_prev);
$cal_next_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($qs_next);

$cal_month_label = date('F Y', strtotime($cal_first));
$cal_days_in_month = (int)date('t', strtotime($cal_first));
$cal_first_weekday = (int)date('N', strtotime($cal_first)); // 1 (Mon) .. 7 (Sun)
$cal_offset = $cal_first_weekday - 1;
$today_ymd = date('Y-m-d');

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
    <title>Doctor Dashboard - Medkit</title>

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

        /* TODAY APPOINTMENTS LIST */
        .today-appointments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .today-appointment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #e6edf7;
            background: #f6f8fc;
            border-radius: 14px;
        }

        .today-appointment-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .today-appointment-time {
            font-size: 12px;
            font-weight: 700;
            color: #1f2a44;
            background: #ffffff;
            border: 1px solid #e6edf7;
            padding: 6px 10px;
            border-radius: 12px;
            white-space: nowrap;
        }

        .today-appointment-patient {
            font-size: 14px;
            font-weight: 600;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .today-appointment-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #666;
            white-space: nowrap;
        }

        .today-appointment-status .mini-calendar-dot {
            width: 8px;
            height: 8px;
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
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }

        .percentage-circle svg {
            width: 100%;
            height: 100%;
            display: block;
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

        /* DASHBOARD CHARTS (Chart.js) */
        .medikit-chart-square {
            width: 160px;
            height: 160px;
            margin: 0 auto;
            position: relative;
        }

        .medikit-chart-bar {
            width: 100%;
            height: 150px;
            position: relative;
        }

        .medikit-chart-area {
            width: 100%;
            height: 120px;
            position: relative;
        }

        .medikit-chart-square canvas,
        .medikit-chart-bar canvas,
        .medikit-chart-area canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* MINI CALENDAR */
        .mini-calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }

        .mini-calendar-title {
            font-size: 15px;
            font-weight: 700;
            color: #1f2a44;
        }

        .mini-calendar-nav {
            display: flex;
            gap: 8px;
        }

        .mini-calendar-nav a {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #f8f9fa;
            border: 1px solid #e6edf7;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .mini-calendar-nav a:hover {
            background: #e9ecef;
        }

        .mini-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .mini-calendar-weekday {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: #999;
        }

        .mini-calendar-day {
            text-align: center;
            background: #f6f8fc;
            border: 1px solid #e6edf7;
            border-radius: 12px;
            padding: 8px 0;
            min-height: 54px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .mini-calendar-day.empty {
            background: transparent;
            border: none;
        }

        .mini-calendar-day.today {
            background: #e8f0fe;
            border-color: #5a8dee;
        }

        .mini-calendar-date {
            font-size: 13px;
            font-weight: 700;
            color: #1f2a44;
            line-height: 1;
        }

        .mini-calendar-dots {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin-top: 7px;
            height: 8px;
        }

        .mini-calendar-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .mini-calendar-dot.scheduled {
            background: #3b82f6;
        }

        .mini-calendar-dot.completed {
            background: #10b981;
        }

        .mini-calendar-dot.cancelled {
            background: #ef4444;
        }

        .mini-calendar-legend {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
            font-size: 11px;
            color: #666;
        }

        .mini-calendar-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
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
                <span>Medkit</span>
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
                    <div class="speciality"><?= htmlspecialchars($doctor_speciality_label) ?></div>

                    <!-- Stat Boxes -->
                    <div class="stat-boxes">
                        <div class="stat-box purple">
                            <div class="stat-box-label">Appointments</div>
                            <div class="stat-box-value"><?= $todayAppointments ?>+</div>
                        </div>
                        <div class="stat-box pink">
                            <div class="stat-box-label">Scheduled</div>
                            <div class="stat-box-value"><?= $todayScheduled ?>+</div>
                        </div>
                        <div class="stat-box green">
                            <div class="stat-box-label">Completed</div>
                            <div class="stat-box-value"><?= $todayCompleted ?>+</div>
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
                                <div class="medikit-chart-square">
                                    <canvas id="appointmentsDonut" aria-label="Appointments chart" role="img"></canvas>
                                </div>
                                <div style="margin-top: -100px; font-size: 24px; font-weight: 700; color: #333;">
                                    <?= (int)$todayAppointments ?>
                                </div>
                                <div style="font-size: 13px; color: #999;">Total</div>
                            </div>

                            <div style="font-size: 13px;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle" style="color: #3b82f6; font-size: 8px;"></i> Scheduled: <?= (int)$todayScheduled ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="fas fa-circle" style="color: #10b981; font-size: 8px;"></i> Completed: <?= (int)$todayCompleted ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-circle" style="color: #ef4444; font-size: 8px;"></i> Cancelled: <?= (int)$todayCancelled ?></span>
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

                            <div class="medikit-chart-bar mb-3">
                                <canvas id="performanceBar" aria-label="Performance chart" role="img"></canvas>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-center flex-fill">
                                    <div style="color: #10b981; font-size: 20px; font-weight: 700;"><?= (int)$avgAppointmentsPerDay ?></div>
                                    <div style="font-size: 12px; color: #999;">Avg. Appointments</div>
                                </div>
                                <div class="text-center flex-fill">
                                    <div style="color: #10b981; font-size: 20px; font-weight: 700;"><?= (int)$avgPatientsPerDay ?></div>
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
                            <div style="font-size: 28px; font-weight: 700; color: #3b82f6; margin-bottom: 15px;">
                                <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$todayRevenue, 0) ?>
                            </div>

                            <div class="medikit-chart-area">
                                <canvas id="revenueArea" aria-label="Revenue chart" role="img"></canvas>
                            </div>

                            <div style="font-size: 11px; margin-top: 10px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><i class="fas fa-circle" style="color: #10b981; font-size: 6px;"></i> <?= htmlspecialchars($rev_line_1['method']) ?>: <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$rev_line_1['total'], 0) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span><i class="fas fa-circle" style="color: #3b82f6; font-size: 6px;"></i> <?= htmlspecialchars($rev_line_2['method']) ?>: <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$rev_line_2['total'], 0) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-circle" style="color: #a78bfa; font-size: 6px;"></i> <?= htmlspecialchars($rev_line_3['method']) ?>: <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$rev_line_3['total'], 0) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Appointments Feed -->
                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="card-title mb-0">Today's Appointments</h6>
                                <a href="booking.php" class="btn btn-sm btn-outline-primary fw-semibold">View All</a>
                            </div>
                            <div class="text-muted" style="font-size: 13px; margin-bottom: 15px;">Live schedule for <?= htmlspecialchars(date('d M, Y')) ?></div>

                            <?php if (empty($todayAppointmentsList)): ?>
                                <div class="text-center py-4" style="background:#f6f8fc;border:1px solid #e6edf7;border-radius:14px;">
                                    <div class="fw-semibold" style="color:#1f2a44;">No appointments today</div>
                                    <div class="text-muted" style="font-size: 13px;">New bookings will appear here.</div>
                                </div>
                            <?php else: ?>
                                <div class="today-appointments-list">
                                    <?php foreach ($todayAppointmentsList as $appt): ?>
                                        <?php
                                        $pfirst = trim((string)($appt['patient_firstname'] ?? ''));
                                        $plast = trim((string)($appt['patient_lastname'] ?? ''));
                                        $patientName = trim($pfirst . ' ' . $plast);
                                        if ($patientName === '') {
                                            $patientName = 'Patient';
                                        }

                                        $st = (string)($appt['start_time'] ?? '');
                                        $et = (string)($appt['end_time'] ?? '');
                                        $timeLabel = 'Time TBA';
                                        if ($st !== '') {
                                            $timeLabel = date('g:i A', strtotime($st));
                                            if ($et !== '') {
                                                $timeLabel .= ' - ' . date('g:i A', strtotime($et));
                                            }
                                        }

                                        $statusRaw = strtolower(trim((string)($appt['status'] ?? '')));
                                        $dotClass = 'scheduled';
                                        $statusLabel = 'Scheduled';
                                        if ($statusRaw === 'visited') {
                                            $dotClass = 'completed';
                                            $statusLabel = 'Completed';
                                        } elseif ($statusRaw === 'rejected') {
                                            $dotClass = 'cancelled';
                                            $statusLabel = 'Cancelled';
                                        }
                                        ?>
                                        <div class="today-appointment-item">
                                            <div class="today-appointment-left">
                                                <div class="today-appointment-time"><?= htmlspecialchars($timeLabel) ?></div>
                                                <div class="today-appointment-patient" title="<?= htmlspecialchars($patientName) ?>">
                                                    <?= htmlspecialchars($patientName) ?>
                                                </div>
                                            </div>
                                            <div class="today-appointment-status" title="<?= htmlspecialchars($statusLabel) ?>">
                                                <span class="mini-calendar-dot <?= htmlspecialchars($dotClass) ?>"></span>
                                                <span><?= htmlspecialchars($statusLabel) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                    <div class="profile-card-spec"><?= htmlspecialchars(trim((string)($doctor['specialities'] ?? '')) !== '' ? (string)$doctor['specialities'] : 'General Medicine') ?> - <?= htmlspecialchars($doctor_clinic_label !== '' ? $doctor_clinic_label : 'Clinic') ?></div>

                    <!-- Patients Stats -->
                    <div class="profile-stats-box">
                        <div class="profile-stat-row">
                            <div>
                                <div class="profile-stat-value" style="color: #333;"><?= number_format((int)$totalPatients) ?></div>
                                <div class="profile-stat-label">Patients</div>
                            </div>
                            <div class="percentage-circle" aria-label="Patients with pending/accepted appointments percentage">
                                <svg viewBox="0 0 36 36" role="img" aria-label="Pending patients percentage">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#e5e7eb" stroke-width="3"></circle>
                                    <circle cx="18" cy="18" r="16" fill="none" stroke="#ff6b6b" stroke-width="3" stroke-dasharray="<?= (int)$patientPercent ?>, 100" stroke-linecap="round" transform="rotate(-90 18 18)"></circle>
                                </svg>
                                <div class="percentage-text"><?= (int)$patientPercent ?>%</div>
                            </div>
                        </div>
                    </div>

                    <!-- Surgery & Consultation -->
                    <div class="profile-stat-row" style="margin-top: 20px;">
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Prescriptions</div>
                            <div class="profile-stat-value"><?= number_format((int)$prescriptionsCount) ?></div>
                        </div>
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Consultation</div>
                            <div class="profile-stat-value"><?= number_format((int)$visitedAppointments) ?></div>
                        </div>
                    </div>

                    <!-- Patients & Appointment -->
                    <div class="profile-stat-row" style="margin-top: 15px;">
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Pending</div>
                            <div class="profile-stat-value"><?= number_format((int)$pendingAppointments) ?></div>
                        </div>
                        <div class="profile-stat-item">
                            <div style="font-size: 12px; color: #999; margin-bottom: 5px;">Appointments</div>
                            <div class="profile-stat-value"><?= number_format((int)$totalAppointments) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Card -->
                <div class="card mt-4">
                    <div class="mini-calendar-header">
                        <div class="mini-calendar-title"><?= htmlspecialchars($cal_month_label) ?></div>
                        <div class="mini-calendar-nav">
                            <a href="<?= htmlspecialchars($cal_prev_url) ?>" aria-label="Previous month"><i class="fas fa-chevron-left"></i></a>
                            <a href="<?= htmlspecialchars($cal_next_url) ?>" aria-label="Next month"><i class="fas fa-chevron-right"></i></a>
                        </div>
                    </div>

                    <div class="mini-calendar-grid" aria-label="Appointments calendar">
                        <div class="mini-calendar-weekday">M</div>
                        <div class="mini-calendar-weekday">T</div>
                        <div class="mini-calendar-weekday">W</div>
                        <div class="mini-calendar-weekday">T</div>
                        <div class="mini-calendar-weekday">F</div>
                        <div class="mini-calendar-weekday">S</div>
                        <div class="mini-calendar-weekday">S</div>

                        <?php for ($i = 0; $i < $cal_offset; $i++): ?>
                            <div class="mini-calendar-day empty"></div>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $cal_days_in_month; $day++): ?>
                            <?php
                            $d_str = sprintf('%04d-%02d-%02d', (int)$cal_year, (int)$cal_month, (int)$day);
                            $counts = $calendarCounts[$d_str] ?? [];
                            $scheduled_count = (int)($counts['pending'] ?? 0) + (int)($counts['accepted'] ?? 0);
                            $completed_count = (int)($counts['visited'] ?? 0);
                            $cancelled_count = (int)($counts['rejected'] ?? 0);

                            $classes = 'mini-calendar-day';
                            if ($d_str === $today_ymd) {
                                $classes .= ' today';
                            }

                            $title_parts = [];
                            if ($scheduled_count > 0) $title_parts[] = 'Scheduled: ' . $scheduled_count;
                            if ($completed_count > 0) $title_parts[] = 'Completed: ' . $completed_count;
                            if ($cancelled_count > 0) $title_parts[] = 'Cancelled: ' . $cancelled_count;
                            $title_attr = !empty($title_parts) ? implode(' | ', $title_parts) : '';
                            ?>
                            <div class="<?= htmlspecialchars($classes) ?>" <?= $title_attr !== '' ? 'title="' . htmlspecialchars($title_attr) . '"' : '' ?>>
                                <div class="mini-calendar-date"><?= (int)$day ?></div>
                                <div class="mini-calendar-dots">
                                    <?php if ($scheduled_count > 0): ?><span class="mini-calendar-dot scheduled"></span><?php endif; ?>
                                    <?php if ($completed_count > 0): ?><span class="mini-calendar-dot completed"></span><?php endif; ?>
                                    <?php if ($cancelled_count > 0): ?><span class="mini-calendar-dot cancelled"></span><?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>

                        <?php
                        $total_cells = $cal_offset + $cal_days_in_month;
                        $remaining = (7 - ($total_cells % 7)) % 7;
                        for ($i = 0; $i < $remaining; $i++):
                        ?>
                            <div class="mini-calendar-day empty"></div>
                        <?php endfor; ?>
                    </div>

                    <div class="mini-calendar-legend">
                        <div class="mini-calendar-legend-item"><span class="mini-calendar-dot scheduled"></span> Scheduled</div>
                        <div class="mini-calendar-legend-item"><span class="mini-calendar-dot completed"></span> Completed</div>
                        <div class="mini-calendar-legend-item"><span class="mini-calendar-dot cancelled"></span> Cancelled</div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

        // Dashboard charts (Chart.js)
        (function() {
            if (typeof Chart === 'undefined') return;

            Chart.defaults.font.family = 'Poppins, sans-serif';

            const donutData = <?= json_encode([(int)$todayScheduled, (int)$todayCompleted, (int)$todayCancelled], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const perfLabels = <?= json_encode($performanceLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const perfCounts = <?= json_encode($performanceCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const revLabels = <?= json_encode($revenueLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const revTotals = <?= json_encode($revenueTotals, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const donutEl = document.getElementById('appointmentsDonut');
            if (donutEl) {
                new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: ['Scheduled', 'Completed', 'Cancelled'],
                        datasets: [{
                            data: donutData,
                            backgroundColor: ['#3b82f6', '#10b981', '#ef4444'],
                            borderWidth: 0,
                            hoverOffset: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const v = typeof ctx.raw === 'number' ? ctx.raw : 0;
                                        return ' ' + ctx.label + ': ' + v;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const perfEl = document.getElementById('performanceBar');
            if (perfEl) {
                new Chart(perfEl, {
                    type: 'bar',
                    data: {
                        labels: perfLabels,
                        datasets: [{
                            data: perfCounts,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderRadius: 6,
                            maxBarThickness: 22,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const v = typeof ctx.raw === 'number' ? ctx.raw : 0;
                                        return ' Appointments: ' + v;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#999',
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(230, 237, 247, 0.8)'
                                },
                                ticks: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            const revEl = document.getElementById('revenueArea');
            if (revEl) {
                new Chart(revEl, {
                    type: 'line',
                    data: {
                        labels: revLabels,
                        datasets: [{
                            data: revTotals,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.15)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 0,
                            borderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const v = typeof ctx.raw === 'number' ? ctx.raw : 0;
                                        return ' Revenue: ' + v;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        })();
    </script>

</body>

</html>