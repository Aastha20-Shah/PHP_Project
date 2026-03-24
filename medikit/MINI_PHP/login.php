<?php
session_start();
// This page acts as the central login/role selector
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Login Role - Medkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
        }
        .role-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow-lg" style="width: 30rem;">
            <div class="card-body p-5">
                <div class="text-center mb-5">
                    <i class="fas fa-heart-pulse fa-3x text-primary"></i>
                    <h2 class="h4 fw-bold mt-3">Login to Medkit</h2>
                    <p class="text-muted">Please select your role to proceed.</p>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <a href="loginpatient.php" class="text-decoration-none">
                            <div class="card text-center role-card h-100 border-primary border-3">
                                <div class="card-body p-4">
                                    <i class="fas fa-user fa-3x text-primary mb-3"></i>
                                    <h5 class="fw-bold text-primary">Patient Login</h5>
                                    <p class="text-muted small mb-0">Book appointments, view records.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="login_doctor.php" class="text-decoration-none">
                            <div class="card text-center role-card h-100 border-success border-3">
                                <div class="card-body p-4">
                                    <i class="fas fa-user-md fa-3x text-success mb-3"></i>
                                    <h5 class="fw-bold text-success">Doctor Login</h5>
                                    <p class="text-muted small mb-0">Manage schedule and bookings.</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <a href="index.php" class="text-decoration-none text-muted">
                        <i class="fas fa-arrow-left me-1"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>