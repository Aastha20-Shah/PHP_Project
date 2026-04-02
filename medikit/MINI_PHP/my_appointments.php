<?php
session_start();
include("config.php");
include("billing_helpers.php");

// Security: If user is not logged in, redirect to login page
if (!isset($_SESSION['patient_id'])) {
    header("Location: loginpatient.php");
    exit;
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

$billing_schema_error = '';
try {
    billing_ensure_schema($conn);
} catch (Throwable $e) {
    $billing_schema_error = $e->getMessage();
}

$thank_you_msg = '';
$thank_you_type = 'primary';

// Show a one-time thank-you message when an appointment is marked visited
$stmt_thanks = $conn->prepare("SELECT COUNT(*) AS cnt FROM visit_booking WHERE patient_id = ? AND status = 'visited' AND patient_notified = 0");
if ($stmt_thanks) {
    $stmt_thanks->bind_param("i", $patient_id);
    $stmt_thanks->execute();
    $res_thanks = $stmt_thanks->get_result();
    $row_thanks = $res_thanks ? $res_thanks->fetch_assoc() : null;
    $cnt_thanks = (int)($row_thanks['cnt'] ?? 0);
    if ($cnt_thanks > 0) {
        $thank_you_msg = 'Thank you for visiting! We hope you had a pleasant experience.';
    }
    $stmt_thanks->close();
}

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

// Appointments (with billing + clinic info)
if ($billing_schema_error === '') {
    $query = "SELECT 
                                vb.id AS booking_id,
                                vb.appointment_date,
                                vb.status,
                                u.firstname AS doc_firstname,
                                u.lastname AS doc_lastname,
                                u.clinic_name,
                                u.address AS clinic_address,
                                u.phone_number AS clinic_phone,
                                u.email AS clinic_email,
                                s.doctor_speciality,
                                cb.id AS bill_id,
                                cb.payment_status AS bill_status
                            FROM 
                                visit_booking AS vb
                            JOIN 
                                users AS u ON vb.doctor_id = u.id
                            JOIN 
                                speciality AS s ON vb.speciality_id = s.id
                            LEFT JOIN
                                clinic_bills AS cb ON cb.booking_id = vb.id
                            WHERE 
                                vb.patient_id = ?
                            ORDER BY 
                                vb.appointment_date DESC, vb.id DESC";
} else {
    $query = "SELECT 
                                vb.id AS booking_id,
                                vb.appointment_date,
                                vb.status,
                                u.firstname AS doc_firstname,
                                u.lastname AS doc_lastname,
                                u.clinic_name,
                                u.address AS clinic_address,
                                u.phone_number AS clinic_phone,
                                u.email AS clinic_email,
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
                                vb.appointment_date DESC, vb.id DESC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
$stmt->close();
?>
<?php include('header.php'); ?>
<main class="container my-5">
    <?php if ($thank_you_msg !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($thank_you_type); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($thank_you_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($billing_schema_error !== ''): ?>
        <div class="alert alert-warning">
            Billing is not available right now.
        </div>
    <?php endif; ?>
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

                                    <?php
                                    $clinic_name = trim((string)($appt['clinic_name'] ?? ''));
                                    $clinic_address = trim((string)($appt['clinic_address'] ?? ''));
                                    $clinic_phone = trim((string)($appt['clinic_phone'] ?? ''));
                                    $clinic_email = trim((string)($appt['clinic_email'] ?? ''));
                                    ?>
                                    <div class="mt-2 text-muted small">
                                        <?php if ($clinic_name !== ''): ?>
                                            <div><i class="fas fa-hospital me-2"></i><strong>Clinic:</strong> <?php echo htmlspecialchars($clinic_name); ?></div>
                                        <?php endif; ?>
                                        <?php if ($clinic_address !== ''): ?>
                                            <div><i class="fas fa-location-dot me-2"></i><strong>Address:</strong> <?php echo htmlspecialchars($clinic_address); ?></div>
                                        <?php endif; ?>
                                        <?php if ($clinic_phone !== ''): ?>
                                            <div><i class="fas fa-phone me-2"></i><strong>Phone:</strong> <?php echo htmlspecialchars($clinic_phone); ?></div>
                                        <?php endif; ?>
                                        <?php if ($clinic_email !== ''): ?>
                                            <div><i class="fas fa-envelope me-2"></i><strong>Email:</strong> <?php echo htmlspecialchars($clinic_email); ?></div>
                                        <?php endif; ?>
                                        <?php if ($clinic_name === '' && $clinic_address === '' && $clinic_phone === '' && $clinic_email === ''): ?>
                                            <div>-</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-md-center">
                                    <p class="text-muted mb-0"><i class="fas fa-calendar-alt me-2"></i><?php echo htmlspecialchars(date("F j, Y", strtotime($appt['appointment_date']))); ?></p>
                                </div>
                                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                                    <?php
                                    $raw_status = (string)($appt['status'] ?? '');
                                    $status_class = 'bg-secondary';
                                    $status_label = $raw_status !== '' ? ucfirst($raw_status) : '—';

                                    if ($raw_status === 'visited') {
                                        $status_class = 'bg-info';
                                        $status_label = 'Completed';
                                    } elseif ($raw_status === 'rejected') {
                                        $status_class = 'bg-danger';
                                        $status_label = 'Canceled';
                                    } elseif ($raw_status === 'pending' || $raw_status === 'accepted') {
                                        $status_class = 'bg-success';
                                        $status_label = 'Booked';
                                    }
                                    ?>
                                    <span class="badge rounded-pill fs-6 <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span>

                                    <?php
                                    $bill_id = (int)($appt['bill_id'] ?? 0);
                                    $bill_status = (string)($appt['bill_status'] ?? '');
                                    $bill_badge = 'bg-warning text-dark';
                                    $bill_text = 'Pending';
                                    if ($bill_status === 'paid') {
                                        $bill_badge = 'bg-success';
                                        $bill_text = 'Paid';
                                    }
                                    ?>

                                    <div class="mt-2">
                                        <?php if ($bill_id > 0): ?>
                                            <div class="mb-2">
                                                <span class="badge rounded-pill <?php echo $bill_badge; ?>">Bill: <?php echo htmlspecialchars($bill_text); ?></span>
                                            </div>
                                            <a href="view_bill.php?bill_id=<?php echo $bill_id; ?>" class="btn btn-outline-primary btn-sm">View Bill</a>
                                        <?php elseif (($appt['status'] ?? '') === 'visited'): ?>
                                            <div class="text-muted small">Bill: Not Available</div>
                                        <?php endif; ?>
                                    </div>
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