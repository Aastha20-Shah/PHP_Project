<?php session_start(); ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | KeyVault</title>
    <style>
        /* Reset and basic styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(to right, #0f172a, #0b1e25ff, #14465bff);
            color: #fff;
        }

        .container {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
        }

        h2 {
            font-size: 28px;
            margin-bottom: 25px;
            color: #fff;
        }

        form input[type="email"] {
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
            margin-top: 10px;
            transition: 0.3s;
        }

        form button:hover {
            background-color: #16a085;
        }

        .message {
            margin-top: 15px;
            font-size: 14px;
        }

        .message.success {
            color: #2ecc71;
        }

        .message.error {
            color: #e74c3c;
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
        <h2>Forgot Password</h2>

        <?php
        if (isset($_SESSION['message'])) {
            echo "<div class='message success'>" . $_SESSION['message'] . "</div>";
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error'])) {
            echo "<div class='message error'>" . $_SESSION['error'] . "</div>";
            unset($_SESSION['error']);
        }
        ?>

        <form method="post" action="forgot_password.php">
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send Reset Link</button>
        </form>
    </div>
</body>

</html>