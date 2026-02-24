<?php
session_start();
include 'config.php';

// ✅ Add PHPMailer
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- CASE 1: Handle email verification ---
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id, name FROM users WHERE verification_token=? AND verified=0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($userId, $userName);
        $stmt->fetch();
        $stmt->close();

        // Update user as verified
        $update = $conn->prepare("UPDATE users SET verified=1, verification_token=NULL WHERE id=?");
        $update->bind_param("i", $userId);
        $update->execute();

        // Start session
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $userName;

        // ✅ Redirect directly to dashboard after verification
        echo "<script>alert('Email verified successfully! Welcome $userName.'); window.location.href='DASHBOARD/sidebar.html';</script>";
        exit;
    } else {
        echo "Invalid or already verified token.";
        exit;
    }
}

// --- CASE 2: Handle new registration ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs
    $name = $_POST['fullname'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validate passwords match
    if ($password !== $confirmPassword) {
        echo "<script>alert('Passwords do not match.');window.location.href='register.html';</script>";
        exit;
    }
    // ✅ Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "<script>alert('This email is already registered. Please log in instead.');window.location.href='login.html';</script>";
        exit;
    }
    $check->close();

    // Encrypt password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(16)); // unique email verification token

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, verification_token, verified) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $token);

    if ($stmt->execute()) {
        $userId = $stmt->insert_id;

        // Set session (not yet verified)
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;

        // Verification link (same page)
        $verificationLink = "http://localhost/KeyWallet/KeyWallet/regi.php?token=" . $token;

        // ✅ Send verification email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ashah464@rku.ac.in'; // 🔴 your Gmail
            $mail->Password   = 'blvp phvp mecj rfpn'; // 🔴 your App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('ashah464@rku.ac.in', 'KeyVault');
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'Verify Your Email - KeyVault';
            $mail->Body    = "<h3>Hello $name,</h3>
                              <p>Please click the link below to verify your email:</p>
                              <a href='$verificationLink'>$verificationLink</a>";

            $mail->send();

            // ✅ After registration, stay on register page, just show message
            echo "<script>alert('Account created! Please check your email to verify.'); window.location.href='register.html';</script>";
        } catch (Exception $e) {
            echo "Mailer Error: " . $mail->ErrorInfo;
        }
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
