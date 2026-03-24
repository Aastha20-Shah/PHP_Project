<?php
session_start();
include("config.php");

// Security: If user is not logged in, redirect to login page
if (!isset($_SESSION['patient_id'])) {
    header("Location: loginpatient.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];
$patient_details = null;
$msg = "";
$msg_type = "";

// Check for a message from the session (e.g., after a successful update)
if (isset($_SESSION['message'])) {
    $msg = $_SESSION['message'];
    $msg_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Prepare and execute query to get patient details
$stmt = $conn->prepare("SELECT firstname, lastname, phone_number, date_of_birth, gender, address FROM patient WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patient_details = $result->fetch_assoc();
} else {
    die("Error: Could not retrieve patient details.");
}
$stmt->close();
?>
<?php include('header.php'); ?>
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-4 p-md-5">

                    <?php if ($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <div class="text-center mb-5">
                        <div class="profile-avatar rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <?php echo strtoupper(substr($patient_details['firstname'], 0, 1) . substr($patient_details['lastname'], 0, 1)); ?>
                        </div>
                        <h2 class="fw-bold"><?php echo htmlspecialchars($patient_details['firstname'] . ' ' . $patient_details['lastname']); ?></h2>
                        <p class="text-muted">Patient Profile</p>
                    </div>

                    <div class="profile-details">
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient_details['firstname'] . ' ' . $patient_details['lastname']); ?></p>
                        <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($patient_details['phone_number']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars(date("F j, Y", strtotime($patient_details['date_of_birth']))); ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient_details['gender']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient_details['address']); ?></p>
                    </div>
                    
                    <hr class="my-4">
                    <div class="text-center">
                        <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include('footer.php'); ?>