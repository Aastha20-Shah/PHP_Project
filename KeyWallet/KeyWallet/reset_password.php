<?php
session_start();
include 'config.php';

if (!isset($_GET['token'])) {
    die("Invalid request.");
}

$token = mysqli_real_escape_string($conn, $_GET['token']);
$result = mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$token' AND reset_expires > NOW()");

if (mysqli_num_rows($result) === 0) {
    die("Token is invalid or expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    mysqli_query($conn, "UPDATE users SET password='$password', reset_token=NULL, reset_expires=NULL WHERE reset_token='$token'");
    $_SESSION['message'] = "Password has been reset successfully.";
    header("Location: login.html");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reset Password</title>
    <style>
        /* Reset and basic styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(to right, #0f172a, #0b1e25ff, #14465bff);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #fff;
        }

        .container {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            text-align: center;
        }

        h2 {
            margin-bottom: 25px;
            font-size: 28px;
            color: #fff;
        }

        form input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 8px;
            border: none;
            outline: none;
            font-size: 16px;
        }

        form button {
            width: 100%;
            padding: 12px;
            background-color: #1abc9c;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            color: #fff;
            transition: 0.3s;
        }

        form button:hover {
            background-color: #16a085;
        }

        .message {
            margin-top: 15px;
            font-size: 14px;
            color: #f1c40f;
        }

        @media (max-width: 450px) {
            .container {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Reset Your Password</h2>
        <form method="post">
            <input type="password" name="password" placeholder="New Password" required>
            <button type="submit">Reset Password</button>
        </form>
        <?php
        if (isset($error)) {
            echo "<div class='message'>$error</div>";
        }
        ?>
    </div>
</body>

</html>