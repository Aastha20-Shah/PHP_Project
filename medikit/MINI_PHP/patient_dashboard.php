<?php
session_start();
include("config.php"); // Your database connection

$is_logged_in = isset($_SESSION['patient_id']);
$patient_id = $is_logged_in ? $_SESSION['patient_id'] : null;
$patient_name = $is_logged_in ? $_SESSION['patient_name'] : '';

$doctors = [];
$msg = "";
$msg_type = "";

// Handle messages stored in the session after a redirect
if (isset($_SESSION['message'])) {
    $msg = $_SESSION['message'];
    $msg_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Booking form processing logic
if (isset($_POST['book'])) {
    if (!$is_logged_in) {
        $_SESSION['message'] = "You must be logged in to book. Please <a href='loginpatient.php' class='alert-link'>login</a>.";
        $_SESSION['message_type'] = "danger";
    } else {
        $doctor_id_post = intval($_POST['doctor_id']);
        $time_id = intval($_POST['time_id']);
        $speciality_id = intval($_POST['speciality_id']);
        $note = mysqli_real_escape_string($conn, $_POST['note']);
        $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);

        // The corrected database query to check only for ACTIVE bookings
        $check = mysqli_query($conn, "SELECT * FROM visit_booking 
                                       WHERE doctor_id='$doctor_id_post' 
                                       AND time_id='$time_id' 
                                       AND appointment_date='$appointment_date'
                                       AND (status = 'pending' OR status = 'accepted')");
                                       
        if (mysqli_num_rows($check) > 0) {
            $_SESSION['message'] = "This time slot has already been booked!";
            $_SESSION['message_type'] = "warning";
        } else {
            $insert_query = "INSERT INTO visit_booking (patient_id, doctor_id, speciality_id, time_id, Note, appointment_date) VALUES ('$patient_id', '$doctor_id_post', '$speciality_id', '$time_id', '$note', '$appointment_date')";
            if (mysqli_query($conn, $insert_query)) {
                $_SESSION['message'] = "Appointment request submitted! Awaiting doctor confirmation.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error submitting appointment: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
    }
    
    // Redirect to prevent form resubmission on refresh
    header("Location: patient_dashboard.php");
    exit();
}

// --- Check latest booking status for logged-in user ---
if ($is_logged_in && empty($msg)) { // Only run if no other message is set
    $status_query = "SELECT status, appointment_date FROM visit_booking WHERE patient_id = ? ORDER BY id DESC LIMIT 1";
    
    $stmt = $conn->prepare($status_query);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $latest_appt = $result->fetch_assoc();
            $status = $latest_appt['status'];
            $date = date("F j, Y", strtotime($latest_appt['appointment_date']));

            if ($status == 'accepted') {
                $msg = "🎉 Your appointment for <strong>$date</strong> has been accepted! The doctor is waiting for you.";
                $msg_type = "success";
            } elseif ($status == 'rejected') {
                $msg = "❌ Unfortunately, your recent booking request was rejected. Please try another slot.";
                $msg_type = "danger";
            } elseif ($status == 'visited') {
                $msg = "Thank you for visiting! We hope you had a pleasant experience.";
                $msg_type = "primary";
            }
        }
        $stmt->close();
    }
}

// General search and filtering
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['action']) && !isset($_POST['book'])) {
    $search_term = isset($_POST['search_term']) ? mysqli_real_escape_string($conn, $_POST['search_term']) : '';
    $category_id_filter = isset($_POST['category_id_filter']) ? intval($_POST['category_id_filter']) : 0;
    $speciality_id_filter = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;
    
    $where_clauses = [];
    if (!empty($search_term)) {
        $where_clauses[] = "(u.firstname LIKE '%$search_term%' OR u.lastname LIKE '%$search_term%' OR c.category_name LIKE '%$search_term%' OR s.doctor_speciality LIKE '%$search_term%')";
    }
    if ($category_id_filter > 0) { 
        $where_clauses[] = "c.id = '$category_id_filter'"; 
    }
    if ($speciality_id_filter > 0) { 
        $where_clauses[] = "EXISTS (SELECT 1 FROM doctor_speciality ds_sub WHERE ds_sub.doctor_id = u.id AND ds_sub.speciality_id = '$speciality_id_filter')"; 
    }
    
    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = "WHERE " . implode(' AND ', $where_clauses);
    }

    $query = "SELECT u.id, u.firstname, u.lastname, u.phone_number, u.address, c.category_name, GROUP_CONCAT(DISTINCT s.doctor_speciality SEPARATOR ', ') AS doctor_specialities FROM users u INNER JOIN category c ON u.category_id = c.id INNER JOIN doctor_speciality ds ON ds.doctor_id = u.id INNER JOIN speciality s ON s.id = ds.speciality_id $where_sql GROUP BY u.id";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) { $doctors[] = $row; }
    } else {
        $msg = "Error in search query: " . mysqli_error($conn);
        $msg_type = "danger";
    }
}

$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY category_name");
?>
<?php include('header.php'); ?>
<main>
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 text-white">We Are Always Ready To Help You.</h1>
            <p class="lead text-white-50">Book an appointment with our expert doctors anytime.</p>
            <a href="#find-doctor" class="btn btn-light btn-lg mt-3">Book Appointment</a>
        </div>
    </section>

        <section class="departments-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center mb-5">
                    <h2 class="fw-bold">We Offer Different Departments To Diagnose Your Diseases</h2>
                    <p class="text-muted">Explore our specialized departments for comprehensive care.</p>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <nav>
                        <div class="nav nav-tabs departments-nav justify-content-center" id="nav-tab" role="tablist">
                            <button class="nav-link active" id="nav-cardiac-tab" data-bs-toggle="tab" data-bs-target="#nav-cardiac" type="button" role="tab"><span class="icon"><i class="fas fa-heartbeat"></i></span><span class="title">Cardiac Clinic</span><span class="subtitle">Heart Care</span></button>
                            <button class="nav-link" id="nav-neurology-tab" data-bs-toggle="tab" data-bs-target="#nav-neurology" type="button" role="tab"><span class="icon"><i class="fas fa-brain"></i></span><span class="title">Neurology</span><span class="subtitle">Brain Health</span></button>
                            <button class="nav-link" id="nav-dentistry-tab" data-bs-toggle="tab" data-bs-target="#nav-dentistry" type="button" role="tab"><span class="icon"><i class="fas fa-tooth"></i></span><span class="title">Dentistry</span><span class="subtitle">Oral Care</span></button>
                            <button class="nav-link" id="nav-gastro-tab" data-bs-toggle="tab" data-bs-target="#nav-gastro" type="button" role="tab"><span class="icon"><i class="fas fa-bone"></i></span><span class="title">Orthopedics</span><span class="subtitle">Bone & Joints</span></button>
                            <a href="#find-doctor" class="nav-link"><span class="icon"><i class="fas fa-search-plus"></i></span><span class="title">Many More</span><span class="subtitle">Check Below</span></a>
                        </div>
                    </nav>
                    
                    <div class="tab-content mt-4" id="nav-tabContent">
                    <!-- Cardiac Clinic -->
                    <div class="tab-pane fade show active" id="nav-cardiac" role="tabpanel">
                        <h3>Cardiac Clinic</h3>
                        <p>
                            Our Cardiac Clinic is dedicated to providing world-class care for all heart and vascular conditions. 
                            With a team of experienced cardiologists, advanced diagnostic tools, and a patient-centered approach, 
                            we ensure complete cardiac wellness—from early prevention to complex surgeries. 
                            We specialize in heart failure management, coronary interventions, and cardiac rehabilitation to help patients regain a healthy lifestyle.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Advanced ECG, 2D Echo, and Stress Testing facilities.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Interventional cardiology and angioplasty services.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Post-operative cardiac rehabilitation and lifestyle counseling.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Preventive heart check-up packages for all age groups.</li>
                        </ul>
                    </div>

                    <!-- Neurology -->
                    <div class="tab-pane fade" id="nav-neurology" role="tabpanel">
                        <h3>Neurology</h3>
                        <p>
                            The Neurology Department at MedCare focuses on the diagnosis and treatment of disorders affecting the brain, spinal cord, and nervous system. 
                            Our neurologists combine expertise with cutting-edge imaging technology to manage everything from migraines and epilepsy to Parkinson’s disease and stroke. 
                            We emphasize early detection, patient education, and personalized care plans to improve neurological health and quality of life.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Comprehensive evaluation of headaches, seizures, and movement disorders.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Stroke management and neuro-rehabilitation programs.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Sleep study and cognitive function assessment.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Specialized neurophysiology and imaging facilities.</li>
                        </ul>
                    </div>

                    <!-- Dentistry -->
                    <div class="tab-pane fade" id="nav-dentistry" role="tabpanel">
                        <h3>Dentistry</h3>
                        <p>
                            Our Dentistry Department offers a complete range of oral care services—from preventive check-ups to advanced cosmetic and restorative procedures. 
                            With the latest digital dental technology and a focus on patient comfort, our dentists ensure a painless and relaxing experience. 
                            We believe a healthy smile contributes to overall wellness, and our care reflects that philosophy.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Teeth cleaning, scaling, and polishing for optimal oral hygiene.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Cosmetic dentistry including whitening, veneers, and smile design.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Root canal treatment, crowns, and bridges using modern techniques.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Orthodontic and pediatric dental care.</li>
                        </ul>
                    </div>

                    <!-- Orthopedics -->
                    <div class="tab-pane fade" id="nav-gastro" role="tabpanel">
                        <h3>Orthopedics</h3>
                        <p>
                            The Orthopedics Department provides expert care for conditions affecting bones, joints, ligaments, muscles, and tendons. 
                            From sports injuries to joint replacements, our orthopedic specialists are committed to restoring movement and improving quality of life. 
                            Using minimally invasive surgical techniques and tailored rehabilitation programs, we help patients recover faster and return to daily activities with confidence.
                        </p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Total knee, hip, and shoulder replacement surgeries.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Fracture management and trauma care.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Arthroscopy and sports injury treatment.</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Physiotherapy and post-surgical rehabilitation programs.</li>
                        </ul>
                    </div>
                </div>

                </div>
            </div>
        </div>
    </section>

    <section class="search-section py-5 bg-light" id="find-doctor">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center mb-4">
                    <h2 class="fw-bold">Find & Book an Appointment</h2>
                </div>
            </div>
            
            <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body p-4">
                    <form method="POST" action="patient_dashboard.php" id="doctor-search-form">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg">
                                <label for="search_term" class="form-label fw-bold small">Search Doctor, Speciality...</label>
                                <input type="text" name="search_term" id="search_term" class="form-control" placeholder="e.g., Cardiology, Dr. Smith">
                            </div>
                            <div class="col-lg" id="speciality-filter-group" style="display:none;">
                                <label for="speciality_id_filter" class="form-label fw-bold small">Filter by Speciality</label>
                                <select name="speciality_id_filter" id="speciality_id_filter" class="form-select">
                                    <option value="">All Specialities</option>
                                </select>
                            </div>
                            <div class="col-lg-auto">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </div>
                        <input type="hidden" name="category_id_filter" id="category_id_filter">
                    </form>
                </div>
            </div>

            <div class="row text-center">
                <div class="col-12 mb-4">
                    <h4 class="fw-bold">Or Browse by Categories</h4>
                </div>
                <?php
                $icon_map = ['cardiology' => 'fas fa-heartbeat', 'dentistry' => 'fas fa-tooth', 'dermatology' => 'fas fa-spa', 'fas fa-head-side-virus', 'general medicine' => 'fas fa-stethoscope', 'gynecology' => 'fas fa-venus', 'neurology' => 'fas fa-brain', 'oncology' => 'fas fa-radiation', 'pediatric' => 'fas fa-child', 'ortho' => 'fas fa-bone', 'physio' => 'fas fa-walking', 'eye' => 'fas fa-eye'];
                if ($categories && mysqli_num_rows($categories) > 0) {
                    mysqli_data_seek($categories, 0);
                    while ($cat = mysqli_fetch_assoc($categories)):
                        $category_name_lower = strtolower($cat['category_name']);
                        $icon_class = 'fas fa-briefcase-medical';
                        foreach ($icon_map as $keyword => $icon) { if (strpos($category_name_lower, $keyword) !== false) { $icon_class = $icon; break; } }
                ?>
                <div class="col-lg-3 col-md-4 col-6 mb-4">
                    <a href="#" class="text-decoration-none category-item" data-category-name="<?php echo htmlspecialchars($cat['category_name']); ?>" data-category-id="<?php echo htmlspecialchars($cat['id']); ?>">
                        <div class="card feature-card h-100 p-3">
                            <div class="icon mx-auto mb-2"><i class="<?php echo $icon_class; ?>"></i></div>
                            <h6 class="fw-bold"><?php echo htmlspecialchars($cat['category_name']); ?></h6>
                        </div>
                    </a>
                </div>
                <?php endwhile; } ?>
            </div>

            <?php if (!empty($doctors)): ?>
                 <h4 class="fw-bold my-4 text-center">Search Results (<?php echo count($doctors); ?>)</h4>
                 <?php foreach ($doctors as $doc): ?>
                 <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <div class="doctor-avatar rounded-circle d-flex align-items-center justify-content-center mx-auto">
                                    <?php echo strtoupper(substr($doc['firstname'], 0, 1) . substr($doc['lastname'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <h5 class="fw-bold mb-1 text-primary">Dr. <?php echo htmlspecialchars($doc['firstname'] . " " . $doc['lastname']); ?></h5>
                                <p class="text-success fw-bold mb-2"><?php echo htmlspecialchars($doc['doctor_specialities']); ?></p>
                                <p class="text-muted small mb-0"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($doc['address']); ?></p>
                            </div>
                            <div class="col-md-3 text-md-end mt-3 mt-md-0">
                                <a href="?doctor_id=<?php echo $doc['id']; ?>#booking-form" class="btn btn-primary btn-sm"><i class="fas fa-calendar-plus me-2"></i>Book Now</a>
                            </div>
                        </div>
                    </div>
                 </div>
                 <?php endforeach; ?>
            <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['book'])): ?>
                <div class="card text-center py-5 mt-4"><div class="card-body"><i class="fas fa-user-doctor fs-1 text-warning mb-3"></i><h4 class="fw-bold">No Doctors Found</h4><p class="text-muted">Please try a different search term.</p></div></div>
            <?php endif; ?>

            <?php if (isset($_GET['doctor_id'])): ?>
                <div class="card mt-4" id="booking-form">
                    <div class="card-body p-4">
                        <h4 class="card-title fw-bold mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Complete Your Booking</h4>
                        <form method="POST" action="patient_dashboard.php">
                            <input type="hidden" name="doctor_id" value="<?php echo intval($_GET['doctor_id']); ?>">
                            <div class="row g-3">
                                <div class="col-md-6"><label for="appointment_date" class="form-label fw-bold">Date</label><input type="date" name="appointment_date" id="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="col-md-6"><label for="time_id" class="form-label fw-bold">Time</label><select name="time_id" id="time_id" class="form-select" required><option value="">-- Select a Date --</option></select></div>
                                <div class="col-md-6"><label for="speciality_id" class="form-label fw-bold">Reason</label><select name="speciality_id" id="speciality_id" class="form-select" required><?php $doctor_id_get = intval($_GET['doctor_id']); $spec_result = mysqli_query($conn, "SELECT s.id, s.doctor_speciality FROM speciality s INNER JOIN doctor_speciality ds ON ds.speciality_id = s.id WHERE ds.doctor_id='$doctor_id_get'"); while ($row = mysqli_fetch_assoc($spec_result)) { echo "<option value='{$row['id']}'>" . htmlspecialchars($row['doctor_speciality']) . "</option>"; } ?></select></div>
                                <div class="col-md-6"><label for="note" class="form-label fw-bold">Note (Optional)</label><input type="text" name="note" id="note" class="form-control" placeholder="e.g., annual check-up"></div>
                            </div>
                            <button type="submit" name="book" class="btn btn-primary mt-3"><i class="fas fa-calendar-check me-2"></i>Finalize Booking</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    

    <section class="testimonial-section py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center mb-5">
                    <h2 class="fw-bold">What Our Patients Say About Our Medical Treatments</h2>
                    <div class="section-divider"></div>
                </div>
            </div>
            <div class="swiper-container testimonials-slider">
                <div class="swiper-wrapper pb-5">
                    <div class="swiper-slide"><div class="testimonial-card"><p>"Booking my appointment was incredibly easy, and the doctor was top-notch. The entire process was seamless. Highly recommend Medkit for anyone's healthcare needs."</p><div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=a" class="rounded-circle me-3" alt="Patient"><div><h6 class="fw-bold mb-0">Naimur Rahman</h6></div></div></div></div>
                    <div class="swiper-slide"><div class="testimonial-card"><p>"The platform is very user-friendly and intuitive. I found a specialist in minutes and got the compassionate care I needed right away. A wonderful experience."</p><div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=b" class="rounded-circle me-3" alt="Patient"><div><h6 class="fw-bold mb-0">Ruhfayed Sakib</h6></div></div></div></div>
                    <div class="swiper-slide"><div class="testimonial-card"><p>"Finally, a healthcare platform that truly works for the patient. Everything from searching for a doctor to finalizing the booking was simple and fast. Five stars!"</p><div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=c" class="rounded-circle me-3" alt="Patient"><div><h6 class="fw-bold mb-0">Shakil Hossain</h6></div></div></div></div>
                    <div class="swiper-slide"><div class="testimonial-card"><p>"A fantastic service that saved me a lot of time and stress. The quality of care I received was excellent, and the staff was very professional. I will be using Medkit again."</p><div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=d" class="rounded-circle me-3" alt="Patient"><div><h6 class="fw-bold mb-0">Jane Doe</h6></div></div></div></div>
                </div>
                <div class="swiper-pagination position-relative mt-4"></div>
            </div>
        </div>
    </section>

        <section class="services-section py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center mb-5">
                    <h2 class="fw-bold">We Offer Different Services To Improve Your Health</h2>
                    <p class="text-muted">Dedicated to providing the best healthcare services for you and your family.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="service-item">
                        <div class="icon"><i class="fas fa-headset"></i></div>
                        <div>
                            <h5 class="fw-bold">24/7 Support</h5>
                            <p class="text-muted mb-0">Our support team is available around the clock to assist you with any questions or concerns.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="service-item">
                        <div class="icon"><i class="fas fa-ambulance"></i></div>
                        <div>
                            <h5 class="fw-bold">Emergency Care</h5>
                            <p class="text-muted mb-0">We provide immediate medical attention in critical situations with our top-tier emergency services.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="service-item">
                        <div class="icon"><i class="fas fa-microscope"></i></div>
                        <div>
                            <h5 class="fw-bold">Modern Technology</h5>
                            <p class="text-muted mb-0">Utilizing the latest medical technology for accurate diagnosis and effective treatments.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="service-item">
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                        <div>
                            <h5 class="fw-bold">Qualified Doctors</h5>
                            <p class="text-muted mb-0">Our team consists of highly skilled and experienced doctors across various specialities.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>
<?php include('footer.php'); ?>
<script>
    $(document).ready(function() {
        $('#appointment_date').on('change', function() {
            var selectedDate = $(this).val(); var doctorId = <?php echo isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0; ?>;
            if (selectedDate && doctorId > 0) {
                $('#time_id').html('<option value="">Loading...</option>');
                $.ajax({ type: 'POST', url: 'get_data.php', data: { action: 'get_times', doctor_id: doctorId, selected_date: selectedDate }, success: function(response) { $('#time_id').html(response); }, error: function() { $('#time_id').html('<option value="">Error</option>'); } });
            } else { $('#time_id').html('<option value="">-- Select a Date --</option>'); }
        });

        $('.category-item').on('click', function(e) {
            e.preventDefault(); 
            var categoryName = $(this).data('category-name');
            var categoryId = $(this).data('category-id');

            $('#search_term').val(categoryName); 
            $('#category_id_filter').val(categoryId);
            $('#speciality_id_filter').val('');
            
            $('#speciality-filter-group').fadeIn();
            
            $.ajax({
                type: 'POST',
                url: 'get_data.php',
                data: {
                    action: 'get_specialities_for_search',
                    category_id: categoryId
                },
                success: function(response) {
                    $('#speciality_id_filter').html('<option value="">All Specialities</option>' + response);
                }
            });
        });

        $('#search_term').on('input', function() {
            var currentVal = $(this).val();
            if (currentVal.trim() === '' || !$('.category-item[data-category-name="' + currentVal + '"]').length) { 
                $('#category_id_filter').val('0'); 
                $('#speciality_id_filter').val(''); 
                $('#speciality-filter-group').fadeOut(200); 
            }
        });

        // Script to trigger tab change on hover for the departments section
        $('.departments-nav .nav-link').on('mouseenter', function() {
            var tab = new bootstrap.Tab(this);
            tab.show();
        });
    });
</script>