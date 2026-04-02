<?php
session_start();
include("config.php");
include("profile_image_helpers.php");

medikit_ensure_profile_image_schema($conn);

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
if ($doctor_id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch doctor details
$doc_query = "SELECT u.*, c.category_name, 
              GROUP_CONCAT(DISTINCT s.doctor_speciality SEPARATOR ', ') AS specialities 
              FROM users u 
              LEFT JOIN category c ON u.category_id = c.id 
              LEFT JOIN doctor_speciality ds ON ds.doctor_id = u.id 
              LEFT JOIN speciality s ON s.id = ds.speciality_id 
              WHERE u.id = ? AND u.role_id = 2 
              GROUP BY u.id";
$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: index.php");
    exit;
}
$doctor = $result->fetch_assoc();

$doctor['profile_image'] = $doctor['profile_image'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dr. <?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?> - Medkit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="custom_style.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8f9fa;
        }

        .hero {
            background: linear-gradient(135deg, #1a76d1, #0f5298);
            color: white;
            padding: 40px 0;
        }

        .info-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .avatar-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e8f0fe;
            color: #1a76d1;
            font-size: 36px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            overflow: hidden;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 2px solid #e8f0fe;
            padding-bottom: 8px;
        }

        .payment-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php include('header.php'); ?>

    <div class="hero text-center text-md-start">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <?php
                    $initials = strtoupper(substr($doctor['firstname'], 0, 1) . substr($doctor['lastname'], 0, 1));

                    $profile_image = (string)($doctor['profile_image'] ?? '');
                    $profile_image = str_replace('\\', '/', $profile_image);
                    $profile_image = ltrim($profile_image, '/');
                    $has_photo = ($profile_image !== ''
                        && strpos($profile_image, 'uploads/doctors/') === 0
                        && file_exists(__DIR__ . '/' . $profile_image));
                    ?>
                    <div class="avatar-circle mx-auto mx-md-0 shadow">
                        <?php if ($has_photo): ?>
                            <img src="<?= htmlspecialchars($profile_image) ?>" alt="Dr. <?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                        <?php else: ?>
                            <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-10">
                    <h2 class="fw-bold mb-1">Dr. <?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?></h2>
                    <p class="fs-5 mb-2 text-white-50"><i class="fas fa-stethoscope me-2"></i><?= htmlspecialchars($doctor['specialities'] ?: 'Specialist') ?></p>
                    <p class="mb-0"><i class="fas fa-map-marker-alt me-2 text-warning"></i><?= htmlspecialchars($doctor['address'] ?: 'Location unlisted') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <div class="row g-4">
            <!-- Left Column: Details -->
            <div class="col-lg-7">

                <div class="card info-card">
                    <div class="card-body p-4">
                        <h4 class="section-title"><i class="fas fa-user-md me-2 text-primary"></i>Doctor's History & Bio</h4>
                        <p><?= nl2br(htmlspecialchars($doctor['bio'] ?: 'No bio available for this doctor.')) ?></p>
                    </div>
                </div>

                <div class="card info-card">
                    <div class="card-body p-4">
                        <h4 class="section-title"><i class="fas fa-graduation-cap me-2 text-primary"></i>Education & Experience</h4>
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <div class="text-muted small">Education/Degrees</div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($doctor['education'] ?: 'Not specified') ?></div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <div class="text-muted small">Experience</div>
                                <div class="fw-bold text-dark"><?= intval($doctor['experience_years']) ?> Years</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card info-card">
                    <div class="card-body p-4">
                        <h4 class="section-title"><i class="fas fa-hospital me-2 text-primary"></i>Clinic Info & Payment</h4>
                        <div class="mb-4">
                            <div class="text-muted small">Clinic/Hospital Name</div>
                            <div class="fw-bold fs-5 text-dark mb-2"><?= htmlspecialchars($doctor['clinic_name'] ?: 'Not specified') ?></div>
                            <button class="btn btn-outline-primary btn-sm fw-bold btn-contact-clinic" data-bs-toggle="modal" data-bs-target="#contactClinicModal" data-phone="<?= htmlspecialchars($doctor['phone_number']) ?>" data-docname="<?= htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']) ?>"><i class="fas fa-phone-alt me-2"></i>Contact Clinic</button>
                        </div>

                        <div class="payment-box">
                            <h6 class="fw-bold text-dark mb-2"><i class="fas fa-info-circle me-2 text-warning"></i>Payment Information</h6>
                            <p class="mb-0 text-dark small"><strong>Please note:</strong> Payment should be done in-person at the clinic/hospital. Contact the hospital directly for more details regarding consultation fees and accepted payment methods. No online payment is required to book this appointment.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Booking -->
            <div class="col-lg-5">
                <div id="booking-form-container">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading appointment slots...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $(document).on('click', '.btn-contact-clinic', function() {
                var phone = $(this).data('phone') || 'Not Available';
                var docName = $(this).data('docname') || '';
                $('#contactClinicPhone').text(phone);
                $('#contactClinicTitle').text('Contact Clinic - Dr. ' + docName);
            });

            var doctorId = <?= $doctor_id ?>;
            var $bookingContainer = $('#booking-form-container');
            var loader = '<div class="slot-message text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading booking form...</div>';

            function loadBookingCard() {
                $bookingContainer.html(loader);
                $.ajax({
                    type: 'POST',
                    url: 'get_data.php',
                    data: {
                        action: 'get_booking_form',
                        doctor_id: doctorId
                    },
                    success: function(response) {
                        $bookingContainer.html(response);
                    },
                    error: function() {
                        $bookingContainer.html('<div class="alert alert-danger mb-0">Error loading booking form. Please refresh.</div>');
                    }
                });
            }

            loadBookingCard();
        });
    </script>
    <!-- ── Contact Clinic Modal ─────────────── -->
    <div class="modal fade" id="contactClinicModal" tabindex="-1" aria-labelledby="contactClinicModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary" id="contactClinicTitle">Contact Clinic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="text-muted mb-2">Phone number</p>
                    <h2 class="fw-bold text-success mb-4" id="contactClinicPhone">xxxxxxxxx</h2>
                    <div class="alert alert-light border-0 bg-light text-start text-muted small mb-0 px-4 py-3">
                        <p class="mb-0">By calling this number, you agree to the <a href="#">Terms & Conditions</a>. If you could not connect with the center, please write to <a href="mailto:support@medkit.com">support@medkit.com</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>