<?php
// Include PHPMailer classes manually
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:\xampp\htdocs\KeyWallet\KeyWallet\PHPMailer\src\Exception.php';
require 'C:\xampp\htdocs\KeyWallet\KeyWallet\PHPMailer\src\PHPMailer.php';
require 'C:\xampp\htdocs\KeyWallet\KeyWallet\PHPMailer\src\SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    // Admin email (your email address)
    $adminEmail = "ashah464@rku.ac.in";

    // --- First Mail: Send message to Admin ---
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ashah464@rku.ac.in'; // your email
        $mail->Password   = 'blvp phvp mecj rfpn';          // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom($email, $name);
        $mail->addAddress($adminEmail, 'KeyVault Admin');

        $mail->isHTML(true);
        $mail->Subject = "New Contact Message from $name";
        $mail->Body    = "
            <h2>New Contact Request</h2>
            <p><b>Name:</b> $name</p>
            <p><b>Email:</b> $email</p>
            <p><b>Message:</b><br>$message</p>
        ";
        $mail->AltBody = "Name: $name\nEmail: $email\nMessage:\n$message";

        $mail->send();

        // --- Second Mail: Auto-reply to User ---
        $reply = new PHPMailer(true);
        $reply->isSMTP();
        $reply->Host       = 'smtp.gmail.com';
        $reply->SMTPAuth   = true;
        $reply->Username   = 'ashah464@rku.ac.in'; 
        $reply->Password   = 'blvp phvp mecj rfpn';
        $reply->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $reply->Port       = 587;

        $reply->setFrom($adminEmail, 'KeyVault Support');
        $reply->addAddress($email, $name);

        $reply->isHTML(true);
        $reply->Subject = "Thank you for contacting KeyVault!";
        $reply->Body    = "
            <h2>Thank You, $name!</h2>
            <p>We have received your message and our support team will get back to you shortly.</p>
            <br>
            <p><b>Your Message:</b></p>
            <blockquote>$message</blockquote>
            <br>
            <p>Regards,<br>KeyVault Support Team</p>
        ";
        $reply->AltBody = "Thank you $name!\n\nWe have received your message:\n$message\n\nRegards,\nKeyVault Support Team";

        $reply->send();

        echo "<script>alert('Your message has been sent successfully. A confirmation email has been sent to you.'); window.location.href='contactus.html';</script>";

    } catch (Exception $e) {
        echo "<script>alert('Mailer Error: {$mail->ErrorInfo}'); window.history.back();</script>";
    }
} else {
    header("Location: contactus.html");
    exit();
}
?>
