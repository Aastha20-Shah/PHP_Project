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

// --- Handle Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input data
    $firstname = filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING);
    $lastname = filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

    // Prepare the UPDATE statement
    $stmt = $conn->prepare("UPDATE patient SET firstname = ?, lastname = ?, gender = ?, address = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $firstname, $lastname, $gender, $address, $patient_id);

    // Execute the statement and set a session message
    if ($stmt->execute()) {
        // IMPORTANT: Update the session name to reflect the change immediately in the navbar
        $_SESSION['patient_name'] = $firstname . " " . $lastname;
        $_SESSION['message'] = "Profile updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating profile. Please try again.";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();

    // Redirect back to the profile page to show the message and prevent form resubmission
    header("Location: profile.php");
    exit;
}

// --- Fetch Current Data for Form (GET Request) ---
$stmt = $conn->prepare("SELECT firstname, lastname, phone_number, date_of_birth, gender, address FROM patient WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patient_details = $result->fetch_assoc();
} else {
    // This should not happen if the user is logged in, but it's good practice
    die("Error: Could not retrieve patient details.");
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile | Medkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="custom_style.css">
</head>
<body>
    <?php include('header.php'); ?>
    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="fw-bold text-center mb-4">Update Profile</h2>
                        <form method="POST" action="edit_profile.php">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="firstname" class="form-label fw-bold">First Name</label>
                                    <input type="text" id="firstname" name="firstname" class="form-control" value="<?php echo htmlspecialchars($patient_details['firstname']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastname" class="form-label fw-bold">Last Name</label>
                                    <input type="text" id="lastname" name="lastname" class="form-control" value="<?php echo htmlspecialchars($patient_details['lastname']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_number" class="form-label fw-bold">Phone Number</label>
                                    <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($patient_details['phone_number']); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label fw-bold">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo htmlspecialchars($patient_details['date_of_birth']); ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label for="gender" class="form-label fw-bold">Gender</label>
                                    <select id="gender" name="gender" class="form-select" required>
                                        <option value="Male" <?php if ($patient_details['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                        <option value="Female" <?php if ($patient_details['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                                        <option value="Other" <?php if ($patient_details['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label fw-bold">Address</label>
                                    <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($patient_details['address']); ?>" required>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <button type="submit" class="btn btn-primary me-2">Save Changes</button>
                                <a href="profile.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include('footer.php'); ?>
</body>
</html>