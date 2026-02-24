<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Key Vault - Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .login-box h2 {
            margin-bottom: 25px;
            font-weight: bold;
            text-align: center;
        }

        .btn-login {
            width: 100%;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #343a40;
            text-align: center;
            margin-bottom: 15px;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="logo">🔑 Key Vault</div>
        <h2>Admin Login</h2>

        <?php
        if (isset($_GET['error'])) {
            echo '<div class="error">' . htmlspecialchars($_GET['error']) . '</div>';
        }
        ?>

        <form action="login_process.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" class="form-control" id="username" required placeholder="Enter username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" class="form-control" id="password" required placeholder="Enter password">
            </div>
            <button type="submit" class="btn btn-primary btn-login">Login</button>
        </form>
    </div>
</body>

</html>