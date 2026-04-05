<?php

include("config.php"); // Ensure config.php is included for DB connection

$is_logged_in = isset($_SESSION['patient_id']);
$patient_name = $is_logged_in ? $_SESSION['patient_name'] : '';
$page_name = basename($_SERVER['PHP_SELF']);
$computed_page_title = 'Medkit';

if ($page_name == 'index.php') $computed_page_title = 'Home';
if ($page_name == 'about.php') $computed_page_title = 'About Us';
if ($page_name == 'faq.php') $computed_page_title = 'FAQ';
if ($page_name == 'help.php') $computed_page_title = 'Contact Us';
if ($page_name == 'profile.php') $computed_page_title = 'My Profile';
if ($page_name == 'edit_profile.php') $computed_page_title = 'Edit Profile';
if ($page_name == 'my_appointments.php') $computed_page_title = 'My Appointments';
if ($page_name == 'my_prescriptions.php') $computed_page_title = 'My Prescriptions';
if ($page_name == 'view_prescription.php') $computed_page_title = 'Prescription';
if ($page_name == 'view_bill.php') $computed_page_title = 'Bill';

if (!isset($page_title) || !is_string($page_title) || trim($page_title) === '') {
    $page_title = $computed_page_title;
} else {
    $page_title = trim($page_title);
}


// --- Notification Actions (patient) ---
if ($is_logged_in && isset($_GET['clear_notifications'])) {
    $patient_id = (int)$_SESSION['patient_id'];

    $clear_stmt = $conn->prepare(
        "UPDATE visit_booking
            SET patient_notified = 1
          WHERE patient_id = ?
            AND status IN ('rejected','visited')
            AND patient_notified = 0"
    );
    if ($clear_stmt) {
        $clear_stmt->bind_param('i', $patient_id);
        $clear_stmt->execute();
        $clear_stmt->close();
    }

    // Redirect back to same page without the action param.
    $redirect_params = $_GET;
    unset($redirect_params['clear_notifications']);
    $redirect_to = basename($_SERVER['PHP_SELF']);
    if (!empty($redirect_params)) {
        $redirect_to .= '?' . http_build_query($redirect_params);
    }
    header("Location: $redirect_to");
    exit;
}


// --- Notification Logic: Fetch Unread Count ---
$unread_count = 0;
$patient_notifications = [];
$latest_completed_notification = null;
if ($is_logged_in) {
    $patient_id = $_SESSION['patient_id'];

    // Fetch count of appointments where status is NOT 'pending' (i.e., changed to accepted/rejected/visited) AND patient_notified = 0
    $count_query = "SELECT COUNT(*) AS count 
                    FROM visit_booking 
                    WHERE patient_id = ? 
                    AND status IN ('rejected','visited') 
                    AND patient_notified = 0";

    $stmt = $conn->prepare($count_query);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unread_count = $result->fetch_assoc()['count'];
        $stmt->close();
    }

    // Fetch unread notification details (latest 5)
    $list_stmt = $conn->prepare(
        "SELECT id, status, appointment_date
           FROM visit_booking
          WHERE patient_id = ?
            AND status IN ('rejected','visited')
            AND patient_notified = 0
          ORDER BY updated_at DESC, id DESC
          LIMIT 5"
    );
    if ($list_stmt) {
        $list_stmt->bind_param('i', $patient_id);
        $list_stmt->execute();
        $res = $list_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $date_val = (string)($row['appointment_date'] ?? '');
            $date_label = $date_val !== '' ? date('F j, Y', strtotime($date_val)) : '';
            $status = (string)($row['status'] ?? '');

            $text = '';
            if ($status === 'rejected') {
                $text = "❌ Appointment" . ($date_label ? " on $date_label" : '') . " was cancelled.";
            } elseif ($status === 'visited') {
                $text = "✅ Appointment" . ($date_label ? " on $date_label" : '') . " completed.";
            }

            if ($text !== '') {
                $patient_notifications[] = [
                    'id' => (int)$row['id'],
                    'status' => $status,
                    'text' => $text,
                ];
            }
        }
        $list_stmt->close();
    }

    // Latest completed notification (for one-time thank-you popup)
    $complete_stmt = $conn->prepare(
        "SELECT id, appointment_date
           FROM visit_booking
          WHERE patient_id = ?
            AND status = 'visited'
            AND patient_notified = 0
          ORDER BY updated_at DESC, id DESC
          LIMIT 1"
    );
    if ($complete_stmt) {
        $complete_stmt->bind_param('i', $patient_id);
        $complete_stmt->execute();
        $latest_completed_notification = $complete_stmt->get_result()->fetch_assoc();
        $complete_stmt->close();
    }
}
// ----------------------------------------------

$clear_notifications_url = '';
if ($is_logged_in) {
    $url_params = $_GET;
    $url_params['clear_notifications'] = 1;
    $clear_notifications_url = basename($_SERVER['PHP_SELF']) . '?' . http_build_query($url_params);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | Medkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <?php $medikit_css_v = (int)@filemtime(__DIR__ . '/custom_style.css'); ?>
    <link rel="stylesheet" href="custom_style.css?v=<?php echo $medikit_css_v; ?>">
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-white py-3">
        <div class="container">
            <a class="navbar-brand text-primary" href="index.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link <?php if ($page_name == 'index.php') echo 'active'; ?>" href="index.php">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Doctors</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php#find-doctor">Find A Doctor</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link <?php if ($page_name == 'faq.php') echo 'active'; ?>" href="faq.php">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link <?php if ($page_name == 'about.php') echo 'active'; ?>" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link <?php if ($page_name == 'help.php') echo 'active'; ?>" href="help.php">Contact</a></li>
                </ul>

                <div class="navbar-nav ms-auto d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="btn-group me-3 medikit-appointment-group">
                            <a href="index.php#find-doctor" class="btn btn-primary">
                                Appointment
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge rounded-pill bg-danger ms-1">
                                        <?php echo (int)$unread_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Toggle notifications</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (empty($patient_notifications)): ?>
                                    <li><span class="dropdown-item-text text-muted small">No new notifications</span></li>
                                <?php else: ?>
                                    <li>
                                        <h6 class="dropdown-header">Notifications</h6>
                                    </li>
                                    <?php foreach ($patient_notifications as $n): ?>
                                        <li><span class="dropdown-item-text small"><?php echo htmlspecialchars($n['text']); ?></span></li>
                                    <?php endforeach; ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($clear_notifications_url); ?>">Clear</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="index.php#find-doctor" class="btn btn-primary me-3 medikit-appointment-btn">
                            Appointment
                        </a>
                    <?php endif; ?>
                    <?php if ($is_logged_in): ?>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> Welcome, <?php echo htmlspecialchars(explode(' ', $patient_name)[0]); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="my_appointments.php">My Appointments</a></li>
                                <li><a class="dropdown-item" href="my_prescriptions.php">My Prescriptions</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="patient_logout.php">Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="nav-link">Login / Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php if ($is_logged_in && !empty($latest_completed_notification['id'])): ?>
        <?php
        $completed_id = (int)$latest_completed_notification['id'];
        $completed_date_val = (string)($latest_completed_notification['appointment_date'] ?? '');
        $completed_date_label = $completed_date_val !== '' ? date('F j, Y', strtotime($completed_date_val)) : '';
        ?>
        <div class="container mt-3" id="medikit-completion-thanks" data-booking-id="<?php echo $completed_id; ?>">
            <div class="alert alert-primary alert-dismissible fade show d-none" role="alert" id="medikit-completion-alert">
                Thank you for visiting! We hope you had a pleasant experience.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <script>
            (function() {
                var wrap = document.getElementById('medikit-completion-thanks');
                if (!wrap) return;

                var bookingId = wrap.getAttribute('data-booking-id') || '';
                var alertEl = document.getElementById('medikit-completion-alert');
                if (!alertEl) return;

                var key = 'medikit_thanks_seen_' + bookingId;
                try {
                    if (bookingId && window.localStorage && localStorage.getItem(key)) {
                        wrap.remove();
                        return;
                    }
                } catch (e) {
                    // If storage is blocked, we'll just show the alert.
                }

                alertEl.classList.remove('d-none');
                var closeBtn = alertEl.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        try {
                            if (bookingId && window.localStorage) {
                                localStorage.setItem(key, '1');
                            }
                        } catch (e) {}
                    });
                }
            })();
        </script>
    <?php endif; ?>
    </header>
    <?php if ($page_name != 'index.php' && $page_name != 'doctor_details.php' && $page_name != 'loginpatient.php' && $page_name != 'registerpatient.php' && $page_name != 'login.php' && $page_name != 'register.php' && $page_name != 'login_doctor.php'): ?>
        <section class="page-header">
            <div class="container">
                <div class="row">
                    <div class="col-md-12 text-center">
                        <h1><?php echo $page_title; ?></h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb justify-content-center">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title; ?></li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>