<?php
session_start();
include("config.php");

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the posted data
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // SQL statement to check email
    $stmt = $conn->prepare("SELECT id, firstname, lastname, password FROM patient WHERE email=?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            if (password_verify($password, $patient['password'])) {
                $_SESSION['patient_id'] = $patient['id'];
                $_SESSION['patient_name'] = $patient['firstname'] . " " . $patient['lastname'];
                header("Location: index.php"); 
                exit;
            } else {
                $msg = "Invalid Email or Password!";
            }
        } else {
            $msg = "Invalid Email or Password!";
        }
        $stmt->close();
    } else {
        $msg = "Database error. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | Medkit</title>
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
            <div class="col-lg-6 d-flex align-items-center justify-content-center" style="background-color: var(--secondary-color);">
                <div class="p-4 p-md-5 w-100" style="max-width: 550px;">
                    <div class="text-center d-lg-none mb-4">
                        <a class="navbar-brand text-primary fs-2" href="index.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
                    </div>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 p-lg-5">
                            <h2 class="fw-bold mb-2 text-center">Welcome Back!</h2>
                            <p class="text-muted text-center mb-4">Login to your Medkit account.</p>
                            
                            <?php if (!empty($msg)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo $msg; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="loginpatient.php">
                                <div class="mb-3">
                                    <label for="email" class="form-label fw-bold">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label fw-bold">Password</label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">Login</button>
                                </div>
                            </form>

                            <div class="text-center mt-3">
                                <a href="registerpatient.php" class="small text-decoration-none me-3"><i class="fas fa-user-plus"></i> New User? Sign Up</a>
                                <a href="login.php" class="small text-decoration-none"><i class="fas fa-arrow-left"></i> Back to Selector</a>
                            </div>
                        </div>
                    </div>
                    <p class="text-center text-muted mt-4">Don't have an account? <a href="registerpatient.php" class="fw-bold text-primary text-decoration-none">Sign up</a>.</p>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-flex align-items-center justify-content-center auth-image-half">
                <div class="text-center p-5">
                    <a class="navbar-brand text-white fs-1 mb-4 d-block" href="index.php"><i class="fas fa-heart-pulse me-2"></i>Medkit</a>
                    <h1 class="display-4 fw-bold mb-3">Your Health, Our Priority</h1>
                    <p class="lead">Smart way to Book, Care and Cure. Access top doctors and manage your health with ease.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>