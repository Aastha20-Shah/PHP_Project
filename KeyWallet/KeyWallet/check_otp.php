<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enteredOtp = $_POST['otp'];

    if (isset($_SESSION['otp']) && $enteredOtp == $_SESSION['otp']) {
        // OTP correct → finalize login
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['user_name'] = $_SESSION['pending_user_name'];

        // Clear OTP session
        unset($_SESSION['otp']);
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_user_name']);

        echo "<script>alert('Login successful!'); window.location.href='DASHBOARD/sidebar.html';</script>";
    } else {
        echo "<script>alert('Invalid OTP!'); window.location.href='verify_otp.php';</script>";
    }
}
?>
