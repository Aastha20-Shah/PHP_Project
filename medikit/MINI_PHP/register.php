<?php
session_start();
include("config.php");

$msg = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $dob       = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $gender    = mysqli_real_escape_string($conn, $_POST['gender']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $password  = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    $role_id = 2; // Doctor
    $category_id = "NULL";

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
        $msg_type = "danger";
    }

    // Domain validation
    elseif (!preg_match('/\.(com|in|org|net)$/i', $email)) {
        $msg = "Email must end with .com, .in, .org or .net";
        $msg_type = "danger";
    }

    // Age validation
    else {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;

        if ($age <= 30) {
            $msg = "Doctor age must be more than 30 years to register.";
            $msg_type = "danger";
        } elseif ($password !== $cpassword) {
            $msg = "Passwords do not match!";
            $msg_type = "danger";
        } else {
            // continue existing DB checks & insert

            $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
            if (mysqli_num_rows($check) > 0) {
                $msg = "Doctor with this email already exists!";
                $msg_type = "danger";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users 
                (firstname, lastname, phone_number, date_of_birth,email,gender, address, password, role_id, category_id)
                VALUES 
                ('$firstname','$lastname','$phone','$dob','$email','$gender','$address','$hashed_password','$role_id',$category_id)";

                if (mysqli_query($conn, $query)) {
                    $msg = "Doctor registered successfully! You can now <a href='login_doctor.php' class='alert-link'>login</a>.";
                    $msg_type = "success";
                } else {
                    $msg = "Registration failed. Please try again.";
                    $msg_type = "danger";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Registration | Medkit</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="custom_style.css">

    <!-- DOCTOR BACKGROUND -->
    <style>
        .auth-image-half {
            background: linear-gradient(rgba(13, 110, 253, 0.9), rgba(13, 110, 253, 0.9)),
                url('https://plus.unsplash.com/premium_photo-1681843129112-f7d11a2f17e3?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NXx8aG9zcGl0YWx8ZW58MHx8MHx8fDA%3D') no-repeat center center;
            background-size: cover;
            color: #fff;
        }
    </style>
</head>

<body>

    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">

            <!-- LEFT IMAGE -->
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center auth-image-half">
                <div class="text-center p-5">
                    <a class="navbar-brand text-white fs-1 mb-4 d-block" href="index.php">
                        <i class="fas fa-user-doctor me-2"></i>Medkit
                    </a>
                    <h1 class="display-4 fw-bold mb-3">Doctor Registration</h1>
                    <p class="lead">
                        Join Medkit and manage patients, appointments and care digitally.
                    </p>
                </div>
            </div>

            <!-- RIGHT FORM -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background-color: var(--secondary-color);">
                <div class="p-4 p-md-5 w-100" style="max-width: 600px;">

                    <div class="text-center d-lg-none mb-4">
                        <a class="navbar-brand text-primary fs-2" href="index.php">
                            <i class="fas fa-user-doctor me-2"></i>Medkit
                        </a>
                    </div>

                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-lg-5">

                            <h2 class="fw-bold mb-2 text-center">Create Doctor Account</h2>
                            <p class="text-muted text-center mb-4">It's quick and easy.</p>

                            <?php if ($msg): ?>
                                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
                                    <?php echo $msg; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">First Name</label>
                                        <input type="text" name="firstname" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Last Name</label>
                                        <input type="text" name="lastname" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" name="phone_number" class="form-control" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold">Email</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Gender</label>
                                        <select name="gender" class="form-select" required>
                                            <option value="" disabled selected>-- Select --</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Address</label>
                                        <input type="text" name="address" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Password</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Confirm Password</label>
                                        <input type="password" name="cpassword" class="form-control" required>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        Register
                                    </button>
                                </div>
                            </form>

                        </div>
                    </div>

                    <p class="text-center text-muted mt-4">
                        Already have an account?
                        <a href="login_doctor.php" class="fw-bold text-primary text-decoration-none">Login</a>
                    </p>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>