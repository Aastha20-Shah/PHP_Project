<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard\sidebar.html");
    exit;
}
include 'config.php';
include 'DASHBOARD/logger.php';
// include 'DASHBOARD/auth_check.php';

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // ✅ Added two_factor_enabled column
    $stmt = $conn->prepare("SELECT id, name, password, verified, two_factor_enabled FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId, $userName, $hashedPassword, $verified, $twoFactor);
        $stmt->fetch();

        if ($verified == 0) {
            echo "<script>alert('Please verify your email first.'); window.location.href='login.html';</script>";
            exit;
        }

        if (password_verify($password, $hashedPassword)) {
            if ($twoFactor == 1) {
                // ✅ 2FA enabled → Generate OTP
                $otp = rand(100000, 999999);
                $_SESSION['pending_user_id']   = $userId;
                $_SESSION['pending_user_name'] = $userName;
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_expiry'] = time() + 300; // 5 min expiry
                logEvent($conn, $_SESSION['user_id'], $_SESSION['pending_user_name'], 'User logged in via 2FA', "Waring");


                // Send OTP via PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'ashah464@rku.ac.in'; // change
                    $mail->Password   = 'blvp phvp mecj rfpn'; // change
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;

                    $mail->setFrom('ashah464@rku.ac.in', 'KeyVault');
                    $mail->addAddress($email, $userName);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your OTP Code - KeyVault';
                    $mail->Body    = "<h3>Hello $userName,</h3>
                                      <p>Your OTP code is: <b>$otp</b></p>
                                      <p>Valid for 5 minutes.</p>";

                    $mail->send();

                    echo "<script>alert('OTP sent to your email!'); window.location.href='verify_otp.php';</script>";
                } catch (Exception $e) {
                    echo "Mailer Error: " . $mail->ErrorInfo;
                }
            } else {
                // ✅ 2FA disabled → Direct login
                $_SESSION['user_id']   = $userId;
                $_SESSION['user_name'] = $userName;
                $names = explode(' ', $userName);
                $initials = '';
                foreach ($names as $n) {
                    $initials .= strtoupper($n[0]);
                }
                logEvent($conn, $userId, $userName, 'User logged in', "Warning");

                echo "<script>alert('Welcome $userName!'); window.location.href='DASHBOARD/sidebar.html';</script>";
            }
        } else {
            echo "<script>alert('Invalid password!'); window.location.href='login.html';</script>";
        }
    } else {
        echo "<script>alert('User not found!'); window.location.href='login.html';</script>";
    }
}
