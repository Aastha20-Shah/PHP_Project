<?php
session_start();
include("config.php");
include("billing_helpers.php");
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

// Ensure required tables exist (safe to call repeatedly)
if (function_exists('billing_ensure_schema')) {
  billing_ensure_schema($conn);
}

// Doctor info (for header/sidebar)
$doctor_res = mysqli_query($conn, "SELECT firstname, lastname, profile_image FROM users WHERE id = $doctor_id LIMIT 1");
$doctor = $doctor_res ? mysqli_fetch_assoc($doctor_res) : null;
if (!$doctor) {
  $doctor = ['firstname' => 'Doctor', 'lastname' => '', 'profile_image' => ''];
}
$doctor['profile_image'] = $doctor['profile_image'] ?? '';

function medikit_fetch_all(mysqli $conn, string $sql, string $types = '', ...$params): array
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

  $rows = [];
  while ($res && ($r = $res->fetch_assoc())) {
    $rows[] = $r;
  }

  $stmt->close();
  return $rows;
}

function medikit_fetch_scalar(mysqli $conn, string $sql, string $types = '', ...$params)
{
  $rows = medikit_fetch_all($conn, $sql, $types, ...$params);
  if (empty($rows)) {
    return 0;
  }

  $first = $rows[0] ?? [];
  if (!is_array($first) || empty($first)) {
    return 0;
  }

  $value = array_values($first)[0] ?? 0;
  return is_numeric($value) ? ($value + 0) : 0;
}

$currency_symbol = '₹';

// --- Summary metrics ---
$totalAppointments = (int)medikit_fetch_scalar(
  $conn,
  "SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ?",
  'i',
  $doctor_id
);

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

$paidRevenueTotal = (float)medikit_fetch_scalar(
  $conn,
  "SELECT COALESCE(SUM(amount), 0) AS total
     FROM clinic_bills
    WHERE doctor_id = ?
      AND payment_status = 'paid'",
  'i',
  $doctor_id
);

$monthStart = date('Y-m-01 00:00:00');
$paidRevenueThisMonth = (float)medikit_fetch_scalar(
  $conn,
  "SELECT COALESCE(SUM(amount), 0) AS total
     FROM clinic_bills
    WHERE doctor_id = ?
      AND payment_status = 'paid'
      AND created_at >= ?",
  'is',
  $doctor_id,
  $monthStart
);

$thirtyStartDate = (new DateTime('today'))->modify('-29 days')->format('Y-m-d');
$paidRevenueLast30 = (float)medikit_fetch_scalar(
  $conn,
  "SELECT COALESCE(SUM(amount), 0) AS total
     FROM clinic_bills
    WHERE doctor_id = ?
      AND payment_status = 'paid'
      AND DATE(created_at) >= ?",
  'is',
  $doctor_id,
  $thirtyStartDate
);

// --- Appointments trend (last 14 days) ---
$trendStart = (new DateTime('today'))->modify('-13 days')->format('Y-m-d');
$trendEnd = (new DateTime('today'))->format('Y-m-d');

$trendMap = [];
$trendRows = medikit_fetch_all(
  $conn,
  "SELECT appointment_date, COUNT(*) AS total
     FROM visit_booking
    WHERE doctor_id = ?
      AND appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date",
  'iss',
  $doctor_id,
  $trendStart,
  $trendEnd
);
foreach ($trendRows as $r) {
  $d = (string)($r['appointment_date'] ?? '');
  if ($d !== '') {
    $trendMap[$d] = (int)($r['total'] ?? 0);
  }
}

$trendLabels = [];
$trendCounts = [];
$cursor = new DateTime($trendStart);
$end = new DateTime($trendEnd);
while ($cursor <= $end) {
  $key = $cursor->format('Y-m-d');
  $trendLabels[] = $cursor->format('M j');
  $trendCounts[] = (int)($trendMap[$key] ?? 0);
  $cursor->modify('+1 day');
}

// --- Status breakdown (last 30 days) ---
$status30 = [
  'pending' => 0,
  'accepted' => 0,
  'visited' => 0,
  'rejected' => 0,
];

$statusRows = medikit_fetch_all(
  $conn,
  "SELECT status, COUNT(*) AS total
     FROM visit_booking
    WHERE doctor_id = ?
      AND appointment_date >= ?
    GROUP BY status",
  'is',
  $doctor_id,
  $thirtyStartDate
);
foreach ($statusRows as $r) {
  $s = strtolower(trim((string)($r['status'] ?? '')));
  if ($s !== '' && array_key_exists($s, $status30)) {
    $status30[$s] = (int)($r['total'] ?? 0);
  }
}

// --- Revenue trend (last 6 months) ---
$revStartMonth = (new DateTime('first day of this month'))->modify('-5 months');
$revKeys = [];
$revLabels = [];
$revTotals = [];
for ($i = 0; $i < 6; $i++) {
  $k = $revStartMonth->format('Y-m');
  $revKeys[] = $k;
  $revLabels[] = $revStartMonth->format('M Y');
  $revTotals[] = 0;
  $revStartMonth->modify('+1 month');
}

$revQueryStart = (new DateTime('first day of this month'))->modify('-5 months')->format('Y-m-01 00:00:00');
$revRows = medikit_fetch_all(
  $conn,
  "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
     FROM clinic_bills
    WHERE doctor_id = ?
      AND payment_status = 'paid'
      AND created_at >= ?
    GROUP BY ym
    ORDER BY ym",
  'is',
  $doctor_id,
  $revQueryStart
);

$revMap = [];
foreach ($revRows as $r) {
  $ym = (string)($r['ym'] ?? '');
  if ($ym !== '') {
    $revMap[$ym] = (float)($r['total'] ?? 0);
  }
}

foreach ($revKeys as $idx => $ym) {
  $revTotals[$idx] = (float)($revMap[$ym] ?? 0);
}

// --- Payment methods (last 30 days) ---
$paymentRows = medikit_fetch_all(
  $conn,
  "SELECT payment_method, COALESCE(SUM(amount), 0) AS total
     FROM (
           SELECT CASE
                    WHEN LOWER(TRIM(payment_method)) = 'cash' THEN 'Cash'
                    ELSE 'Online'
                  END AS payment_method,
                  amount
             FROM clinic_bills
            WHERE doctor_id = ?
              AND payment_status = 'paid'
              AND DATE(created_at) >= ?
          ) t
    GROUP BY payment_method
    ORDER BY total DESC",
  'is',
  $doctor_id,
  $thirtyStartDate
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics - Doctor Panel</title>

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

    .stat-card {
      background: #f8fafc;
      border: 1px solid #eef2f7;
      border-radius: 14px;
      padding: 14px 16px;
      height: 100%;
    }

    .stat-label {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 6px;
    }

    .stat-value {
      font-size: 22px;
      font-weight: 800;
      color: #111827;
      line-height: 1.1;
    }

    .stat-sub {
      font-size: 12px;
      color: #6b7280;
      margin-top: 6px;
    }

    .chart-card {
      border: 1px solid #eef2f7;
      border-radius: 14px;
      padding: 14px 16px;
      background: #fff;
      height: 100%;
    }

    .chart-title {
      font-size: 14px;
      font-weight: 700;
      color: #111827;
    }

    .chart-sub {
      font-size: 12px;
      color: #6b7280;
      margin-top: 2px;
    }

    .chart-wrap {
      height: 260px;
      margin-top: 10px;
    }

    .chart-wrap.sm {
      height: 230px;
    }

    .mini-legend {
      margin-top: 10px;
      font-size: 12px;
      color: #6b7280;
    }

    .legend-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 4px 0;
      border-top: 1px dashed #eef2f7;
    }

    .legend-item:first-child {
      border-top: none;
    }

    .legend-left {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #111827;
      font-weight: 600;
    }

    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }

    .dot.pending {
      background: #f59e0b;
    }

    .dot.accepted {
      background: #3b82f6;
    }

    .dot.visited {
      background: #10b981;
    }

    .dot.rejected {
      background: #ef4444;
    }

    .kv-table th,
    .kv-table td {
      font-size: 13px;
      padding: 10px 10px;
      border-color: #eef2f7 !important;
    }

    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
      }

      .sidebar {
        display: none;
      }

      .breadcrumb-bar {
        margin: 12px;
      }

      .panel-card {
        margin: 0 12px 12px;
      }

      .chart-wrap {
        height: 220px;
      }

      .chart-wrap.sm {
        height: 200px;
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
      <a href="doctor_analytics.php" class="nav-item active">
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

  <div class="main-content">
    <div class="breadcrumb-bar">
      <div class="breadcrumb-title">
        <span>Analytics</span>
        <i class="fas fa-chevron-right" style="font-size: 14px; margin: 0 8px;"></i>
        <i class="fas fa-chart-line"></i>
      </div>
      <div class="breadcrumb-links">
        <a href="doctor_dashboard.php" style="color: #5a8dee; font-weight: 600; text-decoration: none;">Dashboard</a>
        <span>/</span>
        <span style="color: #5a8dee; font-weight: 600;">Analytics</span>
      </div>
    </div>

    <div class="panel-card">
      <div class="row g-3">
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Total Appointments</div>
            <div class="stat-value"><?= number_format((int)$totalAppointments) ?></div>
            <div class="stat-sub">All-time</div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Upcoming</div>
            <div class="stat-value"><?= number_format((int)$upcomingAppointments) ?></div>
            <div class="stat-sub">Pending + Accepted (from today)</div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Patients</div>
            <div class="stat-value"><?= number_format((int)$totalPatients) ?></div>
            <div class="stat-sub">Distinct booked patients</div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= number_format((int)$visitedAppointments) ?></div>
            <div class="stat-sub">Visited (all-time)</div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Cancelled</div>
            <div class="stat-value"><?= number_format((int)$rejectedAppointments) ?></div>
            <div class="stat-sub">Rejected (all-time)</div>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="stat-card">
            <div class="stat-label">Revenue</div>
            <div class="stat-value"><?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$paidRevenueLast30, 2) ?></div>
            <div class="stat-sub">Paid last 30 days • This month: <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$paidRevenueThisMonth, 2) ?></div>
          </div>
        </div>
      </div>

      <hr class="my-4">

      <div class="row g-3">
        <div class="col-lg-8">
          <div class="chart-card">
            <div class="chart-title">Appointments Trend</div>
            <div class="chart-sub">Last 14 days</div>
            <div class="chart-wrap"><canvas id="appointmentsTrend"></canvas></div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="chart-card">
            <div class="chart-title">Status Breakdown</div>
            <div class="chart-sub">Last 30 days</div>
            <div class="chart-wrap sm"><canvas id="statusDonut"></canvas></div>
            <div class="mini-legend">
              <div class="legend-item">
                <div class="legend-left"><span class="dot pending"></span>Pending</div>
                <div><?= number_format((int)$status30['pending']) ?></div>
              </div>
              <div class="legend-item">
                <div class="legend-left"><span class="dot accepted"></span>Accepted</div>
                <div><?= number_format((int)$status30['accepted']) ?></div>
              </div>
              <div class="legend-item">
                <div class="legend-left"><span class="dot visited"></span>Completed</div>
                <div><?= number_format((int)$status30['visited']) ?></div>
              </div>
              <div class="legend-item">
                <div class="legend-left"><span class="dot rejected"></span>Cancelled</div>
                <div><?= number_format((int)$status30['rejected']) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="chart-card">
            <div class="chart-title">Revenue Trend</div>
            <div class="chart-sub">Paid bills • Last 6 months</div>
            <div class="chart-wrap"><canvas id="revenueLine"></canvas></div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="chart-card">
            <div class="chart-title">Payments</div>
            <div class="chart-sub">Paid last 30 days</div>
            <?php if (empty($paymentRows)): ?>
              <div class="text-muted small mt-3">No paid bills in the last 30 days.</div>
            <?php else: ?>
              <div class="table-responsive mt-3">
                <table class="table table-sm table-bordered kv-table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Method</th>
                      <th style="width: 140px;">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($paymentRows as $pr): ?>
                      <?php
                      $method = trim((string)($pr['payment_method'] ?? ''));
                      $total = (float)($pr['total'] ?? 0);
                      ?>
                      <tr>
                        <td><?= htmlspecialchars($method !== '' ? $method : '-') ?></td>
                        <td><?= htmlspecialchars($currency_symbol) ?><?= number_format($total, 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="text-muted small mt-2">All-time paid revenue: <?= htmlspecialchars($currency_symbol) ?><?= number_format((float)$paidRevenueTotal, 2) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <hr class="my-4">

      <div class="row g-3">
        <div class="col-md-6">
          <div class="stat-card">
            <div class="stat-label">Pending vs Accepted (all-time)</div>
            <div class="stat-sub" style="margin-top:0;">Pending: <strong><?= number_format((int)$pendingAppointments) ?></strong> • Accepted: <strong><?= number_format((int)$acceptedAppointments) ?></strong></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="stat-card">
            <div class="stat-label">Quick totals</div>
            <div class="stat-sub" style="margin-top:0;">Completed: <strong><?= number_format((int)$visitedAppointments) ?></strong> • Cancelled: <strong><?= number_format((int)$rejectedAppointments) ?></strong></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

    // Charts
    (function() {
      if (typeof Chart === 'undefined') return;

      Chart.defaults.font.family = 'Poppins, sans-serif';

      const trendLabels = <?= json_encode($trendLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const trendCounts = <?= json_encode($trendCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

      const statusData = <?= json_encode([(int)$status30['pending'], (int)$status30['accepted'], (int)$status30['visited'], (int)$status30['rejected']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

      const revLabels = <?= json_encode($revLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const revTotals = <?= json_encode($revTotals, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

      const trendEl = document.getElementById('appointmentsTrend');
      if (trendEl) {
        new Chart(trendEl, {
          type: 'line',
          data: {
            labels: trendLabels,
            datasets: [{
              data: trendCounts,
              borderColor: '#5a8dee',
              backgroundColor: 'rgba(90, 141, 238, 0.16)',
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
                  color: '#9ca3af',
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

      const donutEl = document.getElementById('statusDonut');
      if (donutEl) {
        new Chart(donutEl, {
          type: 'doughnut',
          data: {
            labels: ['Pending', 'Accepted', 'Completed', 'Cancelled'],
            datasets: [{
              data: statusData,
              backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
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
              }
            }
          }
        });
      }

      const revEl = document.getElementById('revenueLine');
      if (revEl) {
        new Chart(revEl, {
          type: 'line',
          data: {
            labels: revLabels,
            datasets: [{
              data: revTotals,
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59, 130, 246, 0.12)',
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
                    return ' Revenue: <?= addslashes($currency_symbol) ?>' + Number(v).toLocaleString(undefined, {
                      minimumFractionDigits: 2,
                      maximumFractionDigits: 2
                    });
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
                  color: '#9ca3af',
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
    })();
  </script>
</body>

</html>