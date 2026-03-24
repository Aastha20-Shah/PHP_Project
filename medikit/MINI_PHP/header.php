<?php

include("config.php"); // Ensure config.php is included for DB connection

$is_logged_in = isset($_SESSION['patient_id']);
$patient_name = $is_logged_in ? $_SESSION['patient_name'] : '';
$page_name = basename($_SERVER['PHP_SELF']);
$page_title = 'Medkit';

if ($page_name == 'index.php') $page_title = 'Home';
if ($page_name == 'about.php') $page_title = 'About Us';
if ($page_name == 'faq.php') $page_title = 'FAQ';
if ($page_name == 'help.php') $page_title = 'Contact Us';
if ($page_name == 'profile.php') $page_title = 'My Profile';
if ($page_name == 'edit_profile.php') $page_title = 'Edit Profile';
if ($page_name == 'my_appointments.php') $page_title = 'My Appointments';


// --- Notification Logic: Fetch Unread Count ---
$unread_count = 0;
if ($is_logged_in) {
    $patient_id = $_SESSION['patient_id'];
    
    // Fetch count of appointments where status is NOT 'pending' (i.e., changed to accepted/rejected/visited) AND patient_notified = 0
    $count_query = "SELECT COUNT(*) AS count 
                    FROM visit_booking 
                    WHERE patient_id = ? 
                    AND status != 'pending' 
                    AND patient_notified = 0";
    
    $stmt = $conn->prepare($count_query);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unread_count = $result->fetch_assoc()['count'];
        $stmt->close();
    }
}
// ----------------------------------------------
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
    <link rel="stylesheet" href="custom_style.css">
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
                        <li class="nav-item"><a class="nav-link <?php if($page_name == 'index.php') echo 'active'; ?>" href="index.php">Home</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Doctors</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="index.php#find-doctor">Find A Doctor</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Pages</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="my_appointments.php">My Appointments</a></li>
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="faq.php">FAQ</a></li>
                            </ul>
                        </li>
                        <li class="nav-item"><a class="nav-link <?php if($page_name == 'about.php') echo 'active'; ?>" href="about.php">About</a></li>
                        <li class="nav-item"><a class="nav-link <?php if($page_name == 'help.php') echo 'active'; ?>" href="help.php">Contact</a></li>
                    </ul>
                    
                    <div class="navbar-nav ms-auto d-flex align-items-center">
                        <a href="index.php#find-doctor" class="btn btn-primary me-3">
                            Appointment
                            <?php if ($unread_count > 0): ?>
                                <span class="badge rounded-pill bg-danger ms-1">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <?php if ($is_logged_in): ?>
                            <div class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i> Welcome, <?php echo htmlspecialchars(explode(' ', $patient_name)[0]); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                    <li><a class="dropdown-item" href="my_appointments.php">My Appointments</a></li>
                                    <li><hr class="dropdown-divider"></li>
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
    </header>
    <?php if ($page_name != 'index.php' && $page_name != 'loginpatient.php' && $page_name != 'registerpatient.php' && $page_name != 'login.php' && $page_name != 'register.php' && $page_name != 'login_doctor.php'): ?>
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