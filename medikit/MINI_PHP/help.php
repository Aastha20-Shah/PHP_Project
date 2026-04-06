<?php
session_start();

$flash = '';
$flash_type = 'success';

$form_name = '';
$form_email = '';
$form_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/admin_helpers.php';

    medikit_contact_messages_ensure_schema($conn);

    $form_name = trim((string)($_POST['name'] ?? ''));
    $form_email = trim((string)($_POST['email'] ?? ''));
    $form_message = trim((string)($_POST['message'] ?? ''));

    if ($form_name === '' || $form_email === '' || $form_message === '') {
        $flash = 'Please fill in all fields.';
        $flash_type = 'danger';
    } elseif (strlen($form_name) > 150) {
        $flash = 'Name is too long.';
        $flash_type = 'danger';
    } elseif (strlen($form_email) > 190) {
        $flash = 'Email is too long.';
        $flash_type = 'danger';
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Please enter a valid email address.';
        $flash_type = 'danger';
    } elseif (strlen($form_message) > 5000) {
        $flash = 'Message is too long.';
        $flash_type = 'danger';
    } else {
        $stmt = $conn->prepare('INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)');
        if (!$stmt) {
            $flash = 'Server error. Please try again.';
            $flash_type = 'danger';
        } else {
            $stmt->bind_param('sss', $form_name, $form_email, $form_message);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: help.php?sent=1');
                exit;
            }
            $stmt->close();
            $flash = 'Unable to send message. Please try again.';
            $flash_type = 'danger';
        }
    }
}

if ($flash === '' && isset($_GET['sent'])) {
    $flash = 'Message sent successfully.';
    $flash_type = 'success';
}

// This includes the session logic from your header file
include('header.php');
?>
<main class="container my-5">
    <div class="row g-5">
        <div class="col-lg-6">
            <h3 class="fw-bold mb-4">Contact Information</h3>
            <p class="text-muted mb-4">We're here to help! Reach out to us through any of the methods below, and our team will get back to you as soon as possible.</p>

            <div class="service-item">
                <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <h5 class="fw-bold">Our Location</h5>
                    <p class="text-muted mb-0">Rajkot, Gujarat, IN</p>
                </div>
            </div>

            <hr class="my-4">

            <div class="service-item">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <h5 class="fw-bold">Email Address</h5>
                    <p class="mb-0"><a href="mailto:contact@medkit.com" class="text-muted text-decoration-none">contact@medkit.com</a></p>
                </div>
            </div>

            <hr class="my-4">

            <div class="service-item">
                <div class="icon"><i class="fas fa-phone"></i></div>
                <div>
                    <h5 class="fw-bold">Phone Number</h5>
                    <p class="mb-0"><a href="tel:+911234567890" class="text-muted text-decoration-none">+91 123 456 7890</a></p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body p-4 p-lg-5">
                    <h4 class="fw-bold text-center mb-4">Send us a Message</h4>

                    <?php if ($flash !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                            <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="help_name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="help_name" name="name" placeholder="ABC" value="<?php echo htmlspecialchars($form_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="help_email" class="form-label">Your Email</label>
                            <input type="email" class="form-control" id="help_email" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($form_email, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="help_message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="help_message" name="message" rows="4" placeholder="How can we help you?" required><?php echo htmlspecialchars($form_message, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include('footer.php'); ?>