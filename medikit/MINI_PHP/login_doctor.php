<?php
session_start();
include("config.php");
include("admin_helpers.php");

medikit_doctor_verification_ensure_schema($conn);

$msg = "";
$msg_type = "danger";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $conn->prepare("SELECT id, firstname, lastname, password, verification_status, verification_reason
                            FROM users
                            WHERE email = ? AND role_id = 2
                            LIMIT 1");

    if (!$stmt) {
        $msg = "Login failed. Please try again.";
        $msg_type = "danger";
    } else {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctor = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($doctor && isset($doctor['password']) && password_verify($password, (string)$doctor['password'])) {
            $verification = strtolower(trim((string)($doctor['verification_status'] ?? 'verified')));

            if ($verification !== 'verified') {
                if ($verification === 'pending') {
                    $msg = "Your account is pending admin verification. Please wait for approval.";
                    $msg_type = "warning";
                } elseif ($verification === 'rejected') {
                    $reason = trim((string)($doctor['verification_reason'] ?? ''));
                    $msg = $reason !== ''
                        ? "Your account was rejected by admin. Reason: " . $reason
                        : "Your account was rejected by admin. Please contact support.";
                    $msg_type = "danger";
                } else {
                    $msg = "Your account is not verified yet. Please contact support.";
                    $msg_type = "warning";
                }
            } else {
                $_SESSION['doctor_id'] = (int)$doctor['id'];
                $_SESSION['doctor_name'] = trim((string)($doctor['firstname'] ?? '') . ' ' . (string)($doctor['lastname'] ?? ''));
                header("Location: doctor_dashboard.php");
                exit;
            }
        } else {
            $msg = "Invalid Email or Password!";
            $msg_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Login | Medkit</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="custom_style.css">

    <!-- SAME BACKGROUND STYLE -->
    <style>
        .auth-image-half {
            background: linear-gradient(rgba(26, 118, 209, 0.9), rgba(26, 118, 209, 0.9)),
                url('https://plus.unsplash.com/premium_photo-1681843129112-f7d11a2f17e3?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NXx8aG9zcGl0YWx8ZW58MHx8MHx8fDA%3D') no-repeat center center;
            background-size: cover;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">

            <!-- LEFT LOGIN FORM -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background-color: var(--secondary-color);">
                <div class="p-4 p-md-5 w-100" style="max-width: 550px;">

                    <div class="text-center d-lg-none mb-4">
                        <a class="navbar-brand text-primary fs-2" href="index.php">
                            <i class="fas fa-user-doctor me-2"></i>Medkit
                        </a>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-lg-5">

                            <h2 class="fw-bold mb-2 text-center">Doctor Login</h2>
                            <p class="text-muted text-center mb-4">
                                Login to your Medkit Doctor Account
                            </p>

                            <?php if (!empty($msg)): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?>">
                                    <?php echo htmlspecialchars($msg); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        Login
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-3">
                                <a href="register.php" class="small text-decoration-none me-3">
                                    <i class="fas fa-user-plus"></i> New Doctor? Sign Up
                                </a>
                                <a href="login.php" class="small text-decoration-none">
                                    <i class="fas fa-arrow-left"></i> Back to Selector
                                </a>
                            </div>

                        </div>
                    </div>

                    <p class="text-center text-muted mt-4">
                        Don't have an account?
                        <a href="register.php" class="fw-bold text-primary text-decoration-none">
                            Sign up
                        </a>
                    </p>

                </div>
            </div>

            <!-- RIGHT BACKGROUND SECTION (SAME AS PATIENT) -->
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center auth-image-half">
                <div class="text-center p-5">
                    <a class="navbar-brand text-white fs-1 mb-4 d-block" href="index.php">
                        <i class="fas fa-user-doctor me-2"></i>Medkit
                    </a>
                    <h1 class="display-4 fw-bold mb-3">Doctor Panel</h1>
                    <p class="lead">
                        Manage patients, appointments and care digitally.
                    </p>
                </div>
            </div>

        </div>
    </div>
</body>

</html>