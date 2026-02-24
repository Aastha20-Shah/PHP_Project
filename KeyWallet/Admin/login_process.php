<?php
session_start();
require_once 'config.php'; // Your database connection

if (isset($_POST['username'], $_POST['password'])) {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, password, full_name FROM admin WHERE username = ? LIMIT 1";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) == 1) {
            mysqli_stmt_bind_result($stmt, $id, $hashed_password, $full_name);
            mysqli_stmt_fetch($stmt);

            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true); // Prevent session fixation

                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;

                header("Location: dashboard.php");
                exit;
            } else {
                echo "<script>alert('Username or Password incorrect');
                window.location.href = 'index.php';
                </script>";
                exit;
            }
        } else {
            echo "<script>alert('Username or Password incorrect');
            window.location.href = 'index.php';
            </script>";
            exit;
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "Something went wrong. Try again later.";
    }
    mysqli_close($conn);
} else {
    header("Location: login.php");
    exit;
}
