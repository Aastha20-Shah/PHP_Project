<?php
session_start();
include("config.php"); // Your database connection

$msg = "";
$msg_type = ""; // To handle success or error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
    $dob = filter_input(INPUT_POST, 'date_of_birth', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

    $stmt_check = $conn->prepare("SELECT id FROM patient WHERE phone_number=?");
    if ($stmt_check) {
        $stmt_check->bind_param("s", $phone);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows > 0) {
            $msg = "A patient with this phone number is already registered!";
            $msg_type = "danger";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO patient (firstname, lastname, phone_number, date_of_birth, gender, address) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt_insert) {
                $stmt_insert->bind_param("ssssss", $firstname, $lastname, $phone, $dob, $gender, $address);
                if ($stmt_insert->execute()) {
                    $msg = "Registration successful! You can now <a href='loginpatient.php' class='alert-link'>login</a>.";
                    $msg_type = "success";
                } else {
                    $msg = "An error occurred during registration.";
                    $msg_type = "danger";
                }
                $stmt_insert->close();
            }
        }
        $stmt_check->close();
    } else {
        $msg = "Database error. Please try again.";
        $msg_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | Medkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="custom_style.css">
    <style>
        .auth-image-half {
            background: linear-gradient(rgba(26, 118, 209, 0.9), rgba(26, 118, 209, 0.9)), url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?q=80&w=2070&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center auth-image-half">
                <div class="text-center p-5">
                    <a class="navbar-brand text-white fs-1 mb-4 d-block" href="patient_dashboard.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
                    <h1 class="display-4 fw-bold mb-3">Join Our Community</h1>
                    <p class="lead">Smart way to Book, Care and Cure. Access top doctors and manage your health with ease.</p>
                </div>
            </div>
            <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background-color: var(--secondary-color);">
                <div class="p-4 p-md-5 w-100" style="max-width: 600px;">
                    <div class="text-center d-lg-none mb-4">
                        <a class="navbar-brand text-primary fs-2" href="patient_dashboard.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-lg-5">
                            <h2 class="fw-bold mb-2 text-center">Create an Account</h2>
                            <p class="text-muted text-center mb-4">It's quick and easy.</p>
                            
                            <?php if ($msg): ?>
                            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $msg; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php endif; ?>

                            <form method="POST" action="registerpatient.php">
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="firstname" class="form-label fw-bold">First Name</label><input type="text" id="firstname" name="firstname" class="form-control" required></div>
                                    <div class="col-md-6"><label for="lastname" class="form-label fw-bold">Last Name</label><input type="text" id="lastname" name="lastname" class="form-control" required></div>
                                    <div class="col-md-6"><label for="phone_number" class="form-label fw-bold">Phone Number</label><input type="tel" id="phone_number" name="phone_number" class="form-control" required></div>
                                    <div class="col-md-6"><label for="date_of_birth" class="form-label fw-bold">Date of Birth</label><input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required></div>
                                    <div class="col-12"><label for="gender" class="form-label fw-bold">Gender</label><select id="gender" name="gender" class="form-select" required><option value="" selected disabled>-- Select --</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                                    <div class="col-12"><label for="address" class="form-label fw-bold">Address</label><input type="text" id="address" name="address" class="form-control" required></div>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">Register</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <p class="text-center text-muted mt-4">Already have an account? <a href="loginpatient.php" class="fw-bold text-primary text-decoration-none">Login</a>.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>