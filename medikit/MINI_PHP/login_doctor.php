<?php
session_start();
include("config.php");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT id, firstname, lastname, password 
              FROM users 
              WHERE email='$email' AND role_id = 2
              LIMIT 1";

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $doctor = mysqli_fetch_assoc($result);

        // Verify hashed password
        if (password_verify($password, $doctor['password'])) {

            $_SESSION['doctor_id'] = $doctor['id'];
            $_SESSION['doctor_name'] = $doctor['firstname'] . " " . $doctor['lastname'];

            header("Location: doctor_dashboard.php");
            exit;
        } else {
            $msg = "Invalid Email or Password!";
        }
    } else {
        $msg = "Invalid Email or Password!";
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
                                <div class="alert alert-danger">
                                    <?php echo $msg; ?>
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