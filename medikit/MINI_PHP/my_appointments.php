<?php
session_start();
include("config.php");

// Security: If user is not logged in, redirect to login page
if (!isset($_SESSION['patient_id'])) {
    header("Location: loginpatient.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

// --- Notification Logic: Mark all new status updates as read ---
$update_read_status = "UPDATE visit_booking 
                       SET patient_notified = 1 
                       WHERE patient_id = ? 
                       AND patient_notified = 0";

$stmt_update = $conn->prepare($update_read_status);
if ($stmt_update) {
    $stmt_update->bind_param("i", $patient_id);
    $stmt_update->execute();
    $stmt_update->close();
}
// -----------------------------------------------------------------

$appointments = [];

// ORIGINAL QUERY: This query fetches the patient's appointments
$query = "SELECT 
            vb.appointment_date,
            vb.status,
            u.firstname AS doc_firstname,
            u.lastname AS doc_lastname,
            s.doctor_speciality
          FROM 
            visit_booking AS vb
          JOIN 
            users AS u ON vb.doctor_id = u.id
          JOIN 
            speciality AS s ON vb.speciality_id = s.id
          WHERE 
            vb.patient_id = ?
          ORDER BY 
            vb.appointment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
$stmt->close();
?>
<?php include('header.php'); ?>
<main class="container my-5">
    <?php if (empty($appointments)): ?>
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                <h4 class="fw-bold">No Appointments Found</h4>
                <p class="text-muted">You have not booked any appointments yet.</p>
                <a href="index.php" class="btn btn-primary mt-2">Book an Appointment Now</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php foreach ($appointments as $appt): ?>
                    <div class="card mb-3">
                        <div class="card-body p-4">
                            <div class="row align-items-center">
                                <div class="col-md-5">
                                    <h5 class="fw-bold">Dr. <?php echo htmlspecialchars($appt['doc_firstname'] . ' ' . $appt['doc_lastname']); ?></h5>
                                    <p class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($appt['doctor_speciality']); ?></p>
                                </div>
                                <div class="col-md-4 text-md-center">
                                    <p class="text-muted mb-0"><i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars(date("F j, Y", strtotime($appt['appointment_date']))); ?></p>
                                </div>
                                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                                    <?php
                                        $status_class = 'bg-secondary';
                                        if ($appt['status'] == 'accepted') $status_class = 'bg-success';
                                        if ($appt['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                        if ($appt['status'] == 'rejected') $status_class = 'bg-danger';
                                        if ($appt['status'] == 'visited') $status_class = 'bg-info';
                                    ?>
                                    <span class="badge rounded-pill fs-6 <?php echo $status_class; ?>"><?php echo ucfirst(htmlspecialchars($appt['status'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php include('footer.php'); ?>