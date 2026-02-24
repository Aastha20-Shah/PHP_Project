<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Confirm Lock Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .box {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 350px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            border: none;
            background: #5d78ff;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #4655d6;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>Set Lock Password</h2>
        <form id="lockForm" method="POST" action="update_lock.php">
            <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="6">
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required minlength="6">
            <input type="hidden" name="status" value="1">
            <button type="submit">Save Password</button>
        </form>
    </div>
    <script>
        const form = document.getElementById("lockForm");

        form.addEventListener("submit", function(e) {
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirm_password").value;

            if (password !== confirmPassword) {
                e.preventDefault(); // Stop form submission
                alert("Passwords do not match!");
                return false;
            }
        });
    </script>
</body>

</html>