<?php
session_start();
include 'config.php'; // Database connection
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if user exists
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Generate secure token
        $token = bin2hex(random_bytes(50));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save token in database
        mysqli_query($conn, "UPDATE users SET reset_token='$token', reset_expires='$expires' WHERE email='$email'");

        // Prepare reset link
        $resetLink = "http://localhost/KeyWallet/KeyWallet/reset_password.php?token=$token";
        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'ashah464@rku.ac.in'; // SMTP username
            $mail->Password = 'blvp phvp mecj rfpn';    // SMTP password
            $mail->SMTPSecure = 'tls'; // or 'ssl'
            $mail->Port = 587;         // or 465 for SSL

            // Recipients
            $mail->setFrom('noreply@yourdomain.com', 'KeyVault');
            $mail->addAddress($email, $user['name']); // Recipient

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "
                <h3>Hi {$user['name']},</h3>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <a href='$resetLink' target='_blank'>$resetLink</a>
                <p>This link will expire in 1 hour.</p>
            ";

            $mail->send();
            $_SESSION['message'] = "Password reset link has been sent to your email.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $_SESSION['error'] = "Email not found.";
    }

    header("Location: forgot_password_form.php");
    exit();
}
