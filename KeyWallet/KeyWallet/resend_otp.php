<?php
session_start();
include 'config.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Check if user is in pending login state
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_user_name'])) {
    echo "<script>alert('Session expired. Please login again.'); window.location.href='login.html';</script>";
    exit;
}

$userId   = $_SESSION['pending_user_id'];
$userName = $_SESSION['pending_user_name'];

// ✅ Fetch user email from database
$stmt = $conn->prepare("SELECT email FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userEmail);
$stmt->fetch();
$stmt->close();

// ✅ Generate new OTP
$newOtp = rand(100000, 999999);

// ✅ Save new OTP in session
$_SESSION['otp'] = $newOtp;

// ✅ Send OTP via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ashah464@rku.ac.in';   // 🔴 change
    $mail->Password   = 'blvp phvp mecj rfpn';  // 🔴 change
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('ashah464@rku.ac.in', 'KeyVault');
    $mail->addAddress($userEmail, $userName);

    $mail->isHTML(true);
    $mail->Subject = 'Your Resent OTP Code - KeyVault';
    $mail->Body    = "<h3>Hello $userName,</h3>
                      <p>Your new OTP code is: <b>$newOtp</b></p>
                      <p>Valid for 5 minutes.</p>";

    $mail->send();

    echo "<script>alert('A new OTP has been sent to your email.'); window.location.href='verify_otp.php';</script>";
} catch (Exception $e) {
    echo "Mailer Error: " . $mail->ErrorInfo;
}
?>
