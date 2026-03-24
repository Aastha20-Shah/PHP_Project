<?php
session_start();
include("config.php");

$is_logged_in = isset($_SESSION['patient_id']);
$patient_id   = $is_logged_in ? $_SESSION['patient_id'] : null;
$patient_name = $is_logged_in ? $_SESSION['patient_name'] : '';

$doctors  = [];
$msg      = "";
$msg_type = "";

// ── Session flash messages (after redirect) ───────────────────────────────────
if (isset($_SESSION['message'])) {
    $msg      = $_SESSION['message'];
    $msg_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// ── Booking form processing ───────────────────────────────────────────────────
if (isset($_POST['book'])) {
    if (!$is_logged_in) {
        $_SESSION['message']      = "You must be logged in to book. Please <a href='loginpatient.php' class='alert-link'>login</a>.";
        $_SESSION['message_type'] = "danger";
    } else {
        $doctor_id_post   = intval($_POST['doctor_id']);
        $time_id          = intval($_POST['time_id']);
        $speciality_id    = intval($_POST['speciality_id']);
        $note             = mysqli_real_escape_string($conn, $_POST['note']);
        $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);

        $check = mysqli_query(
            $conn,
            "SELECT id FROM visit_booking
              WHERE doctor_id        = '$doctor_id_post'
                AND time_id          = '$time_id'
                AND appointment_date = '$appointment_date'
                AND status IN ('pending','accepted')"
        );

        if (mysqli_num_rows($check) > 0) {
            $_SESSION['message']      = "This time slot has already been booked!";
            $_SESSION['message_type'] = "warning";
        } else {
            $ins = "INSERT INTO visit_booking
                        (patient_id, doctor_id, speciality_id, time_id, Note, appointment_date)
                    VALUES
                        ('$patient_id','$doctor_id_post','$speciality_id','$time_id','$note','$appointment_date')";
            if (mysqli_query($conn, $ins)) {
                $_SESSION['message']      = "Appointment request submitted! Awaiting doctor confirmation.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message']      = "Error submitting appointment: " . mysqli_error($conn);
                $_SESSION['message_type'] = "danger";
            }
        }
    }
    header("Location: index.php");
    exit();
}

// ── Latest booking status for logged-in patient ───────────────────────────────
if ($is_logged_in && empty($msg)) {
    $stmt = $conn->prepare(
        "SELECT status, appointment_date FROM visit_booking
          WHERE patient_id = ? ORDER BY id DESC LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $date = date("F j, Y", strtotime($row['appointment_date']));
            switch ($row['status']) {
                case 'accepted':
                    $msg      = "🎉 Your appointment for <strong>$date</strong> has been accepted! The doctor is waiting for you.";
                    $msg_type = "success";
                    break;
                case 'rejected':
                    $msg      = "❌ Unfortunately, your recent booking request was rejected. Please try another slot.";
                    $msg_type = "danger";
                    break;
                case 'visited':
                    $msg      = "Thank you for visiting! We hope you had a pleasant experience.";
                    $msg_type = "primary";
                    break;
            }
        }
        $stmt->close();
    }
}

// ── Smart search / filter ─────────────────────────────────────────────────────
// Supports three modes:
//   1. Speciality only  (speciality_id_filter > 0, category_id_filter = 0)
//   2. Category only    (category_id_filter > 0,   speciality_id_filter = 0)
//   3. Both / text      (any combination of the above + free-text)
$did_search = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['book'])) {
    $did_search           = true;
    $search_term          = isset($_POST['search_term'])          ? mysqli_real_escape_string($conn, trim($_POST['search_term'])) : '';
    $category_id_filter   = isset($_POST['category_id_filter'])   ? intval($_POST['category_id_filter'])   : 0;
    $speciality_id_filter = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;

    $where_clauses = [];

    // Free-text search across name, category, speciality
    if ($search_term !== '') {
        $where_clauses[] = "(u.firstname  LIKE '%$search_term%'
                          OR u.lastname   LIKE '%$search_term%'
                          OR c.category_name LIKE '%$search_term%'
                          OR s.doctor_speciality LIKE '%$search_term%')";
    }

    // Category filter — only apply when a specific category is chosen
    if ($category_id_filter > 0) {
        $where_clauses[] = "c.id = '$category_id_filter'";
    }

    // Speciality filter — only apply when a specific speciality is chosen
    // Uses EXISTS so it doesn't conflict with the GROUP_CONCAT join
    if ($speciality_id_filter > 0) {
        $where_clauses[] = "EXISTS (
            SELECT 1 FROM doctor_speciality ds_sub
             WHERE ds_sub.doctor_id    = u.id
               AND ds_sub.speciality_id = '$speciality_id_filter'
        )";
    }

    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $query  = "SELECT u.id, u.firstname, u.lastname, u.phone_number, u.address,
                      c.category_name,
                      GROUP_CONCAT(DISTINCT s.doctor_speciality SEPARATOR ', ') AS doctor_specialities
               FROM users u
               INNER JOIN category c        ON u.category_id   = c.id
               INNER JOIN doctor_speciality ds ON ds.doctor_id  = u.id
               INNER JOIN speciality s       ON s.id            = ds.speciality_id
               $where_sql
               GROUP BY u.id
               ORDER BY u.firstname, u.lastname";

    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $doctors[] = $row;
        }
    } else {
        $msg      = "Error in search query: " . mysqli_error($conn);
        $msg_type = "danger";
    }
}

// ── Categories for browse tiles ───────────────────────────────────────────────
$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY category_name");
?>
<?php include('header.php'); ?>
<main>

    <!-- ═══ HERO ════════════════════════════════════════════════════════════ -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 text-white">We Are Always Ready To Help You.</h1>
            <p class="lead text-white-50">Book an appointment with our expert doctors anytime.</p>
            <a href="#find-doctor" class="btn btn-light btn-lg mt-3">Book Appointment</a>
        </div>
    </section>

    <!-- ═══ DEPARTMENTS ══════════════════════════════════════════════════════ -->
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
                            <button class="nav-link active" id="nav-cardiac-tab" data-bs-toggle="tab" data-bs-target="#nav-cardiac" type="button" role="tab">
                                <span class="icon"><i class="fas fa-heartbeat"></i></span><span class="title">Cardiac Clinic</span><span class="subtitle">Heart Care</span>
                            </button>
                            <button class="nav-link" id="nav-neurology-tab" data-bs-toggle="tab" data-bs-target="#nav-neurology" type="button" role="tab">
                                <span class="icon"><i class="fas fa-brain"></i></span><span class="title">Neurology</span><span class="subtitle">Brain Health</span>
                            </button>
                            <button class="nav-link" id="nav-dentistry-tab" data-bs-toggle="tab" data-bs-target="#nav-dentistry" type="button" role="tab">
                                <span class="icon"><i class="fas fa-tooth"></i></span><span class="title">Dentistry</span><span class="subtitle">Oral Care</span>
                            </button>
                            <button class="nav-link" id="nav-gastro-tab" data-bs-toggle="tab" data-bs-target="#nav-gastro" type="button" role="tab">
                                <span class="icon"><i class="fas fa-bone"></i></span><span class="title">Orthopedics</span><span class="subtitle">Bone &amp; Joints</span>
                            </button>
                            <a href="#find-doctor" class="nav-link">
                                <span class="icon"><i class="fas fa-search-plus"></i></span><span class="title">Many More</span><span class="subtitle">Check Below</span>
                            </a>
                        </div>
                    </nav>

                    <div class="tab-content mt-4" id="nav-tabContent">
                        <div class="tab-pane fade show active" id="nav-cardiac" role="tabpanel">
                            <h3>Cardiac Clinic</h3>
                            <p>Our Cardiac Clinic is dedicated to providing world-class care for all heart and vascular conditions. With a team of experienced cardiologists, advanced diagnostic tools, and a patient-centered approach, we ensure complete cardiac wellness—from early prevention to complex surgeries.</p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Advanced ECG, 2D Echo, and Stress Testing facilities.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Interventional cardiology and angioplasty services.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Post-operative cardiac rehabilitation and lifestyle counseling.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Preventive heart check-up packages for all age groups.</li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="nav-neurology" role="tabpanel">
                            <h3>Neurology</h3>
                            <p>The Neurology Department focuses on disorders of the brain, spinal cord, and nervous system. Our neurologists combine expertise with cutting-edge imaging to manage everything from migraines and epilepsy to Parkinson's disease and stroke.</p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Comprehensive evaluation of headaches, seizures, and movement disorders.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Stroke management and neuro-rehabilitation programs.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Sleep study and cognitive function assessment.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Specialized neurophysiology and imaging facilities.</li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="nav-dentistry" role="tabpanel">
                            <h3>Dentistry</h3>
                            <p>Our Dentistry Department offers a complete range of oral care services—from preventive check-ups to advanced cosmetic and restorative procedures using the latest digital dental technology.</p>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Teeth cleaning, scaling, and polishing for optimal oral hygiene.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Cosmetic dentistry including whitening, veneers, and smile design.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Root canal treatment, crowns, and bridges using modern techniques.</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Orthodontic and pediatric dental care.</li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="nav-gastro" role="tabpanel">
                            <h3>Orthopedics</h3>
                            <p>The Orthopedics Department provides expert care for bones, joints, ligaments, muscles, and tendons. From sports injuries to joint replacements, our specialists use minimally invasive techniques and tailored rehab programs.</p>
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

    <!-- ═══ SEARCH & RESULTS ═════════════════════════════════════════════════ -->
    <section class="search-section py-5 bg-light" id="find-doctor">
        <div class="container">
            <div class="row">
                <div class="col-md-12 text-center mb-4">
                    <h2 class="fw-bold">Find &amp; Book an Appointment</h2>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Search form -->
            <div class="card shadow-sm border-0 mb-5">
                <div class="card-body p-4">
                    <form method="POST" action="index.php" id="doctor-search-form">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg">
                                <label for="search_term" class="form-label fw-bold small">Search Doctor, Speciality…</label>
                                <input type="text" name="search_term" id="search_term" class="form-control"
                                    placeholder="e.g., Cardiology, Dr. Smith"
                                    value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
                            </div>
                            <div class="col-lg" id="speciality-filter-group"
                                style="display:<?php echo (isset($_POST['category_id_filter']) && intval($_POST['category_id_filter']) > 0) ? 'block' : 'none'; ?>;">
                                <label for="speciality_id_filter" class="form-label fw-bold small">Filter by Speciality</label>
                                <select name="speciality_id_filter" id="speciality_id_filter" class="form-select">
                                    <option value="">All Specialities</option>
                                    <?php
                                    // Pre-populate if coming back from a category search
                                    if (isset($_POST['category_id_filter']) && intval($_POST['category_id_filter']) > 0) {
                                        $cat_id_repop = intval($_POST['category_id_filter']);
                                        $sel_spec     = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;
                                        $spec_repop   = mysqli_query(
                                            $conn,
                                            "SELECT id, doctor_speciality FROM speciality
                                              WHERE category_id = '$cat_id_repop'
                                              ORDER BY doctor_speciality"
                                        );
                                        while ($sr = mysqli_fetch_assoc($spec_repop)) {
                                            $selected = ($sr['id'] == $sel_spec) ? 'selected' : '';
                                            echo "<option value='{$sr['id']}' $selected>" . htmlspecialchars($sr['doctor_speciality']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-lg-auto">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <!-- Hidden fields to carry filter state -->
                        <input type="hidden" name="category_id_filter" id="category_id_filter"
                            value="<?php echo isset($_POST['category_id_filter']) ? intval($_POST['category_id_filter']) : 0; ?>">
                    </form>
                </div>
            </div>

            <!-- Category browse tiles -->
            <div class="row text-center" id="category-browse">
                <div class="col-12 mb-4">
                    <h4 class="fw-bold">Or Browse by Categories</h4>
                </div>
                <?php
                $icon_map = [
                    'cardiology'      => 'fas fa-heartbeat',
                    'dentistry'       => 'fas fa-tooth',
                    'dermatology'     => 'fas fa-spa',
                    'general medicine' => 'fas fa-stethoscope',
                    'gynecology'      => 'fas fa-venus',
                    'neurology'       => 'fas fa-brain',
                    'oncology'        => 'fas fa-radiation',
                    'pediatric'       => 'fas fa-child',
                    'ortho'           => 'fas fa-bone',
                    'physio'          => 'fas fa-walking',
                    'eye'             => 'fas fa-eye',
                    'ent'             => 'fas fa-head-side-virus',
                    'psychiatry'      => 'fas fa-comment-medical',
                    'ophthalmology'   => 'fas fa-eye',
                ];
                if ($categories && mysqli_num_rows($categories) > 0) {
                    mysqli_data_seek($categories, 0);
                    while ($cat = mysqli_fetch_assoc($categories)):
                        $name_lower = strtolower($cat['category_name']);
                        $icon_class = 'fas fa-briefcase-medical';
                        foreach ($icon_map as $keyword => $icon) {
                            if (strpos($name_lower, $keyword) !== false) {
                                $icon_class = $icon;
                                break;
                            }
                        }
                ?>
                        <div class="col-lg-3 col-md-4 col-6 mb-4">
                            <a href="#" class="text-decoration-none category-item"
                                data-category-name="<?php echo htmlspecialchars($cat['category_name']); ?>"
                                data-category-id="<?php echo $cat['id']; ?>">
                                <div class="card feature-card h-100 p-3">
                                    <div class="icon mx-auto mb-2"><i class="<?php echo $icon_class; ?>"></i></div>
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($cat['category_name']); ?></h6>
                                </div>
                            </a>
                        </div>
                <?php endwhile;
                } ?>
            </div><!-- /category-browse -->

            <!-- ── RESULTS (loaded via AJAX only on Search click) ─────────────── -->
            <div id="search-results" style="display:none;"></div>

            <!-- ── BOOKING FORM (loaded by Book Now without refresh) ─────────── -->
            <div id="booking-form-container">
                <?php if (isset($_GET['doctor_id'])): ?>
                    <div class="card mt-4" id="booking-form">
                        <div class="card-body p-4">
                            <h4 class="card-title fw-bold mb-3">
                                <i class="fas fa-calendar-alt me-2 text-primary"></i>Complete Your Booking
                            </h4>
                            <form method="POST" action="index.php">
                                <input type="hidden" name="doctor_id" value="<?php echo intval($_GET['doctor_id']); ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="appointment_date" class="form-label fw-bold">Date</label>
                                        <input type="date" name="appointment_date" id="appointment_date"
                                            class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="time_id" class="form-label fw-bold">Time</label>
                                        <select name="time_id" id="time_id" class="form-select" required>
                                            <option value="">-- Select a Date first --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="speciality_id" class="form-label fw-bold">Reason / Speciality</label>
                                        <select name="speciality_id" id="speciality_id" class="form-select" required>
                                            <?php
                                            $doc_id = intval($_GET['doctor_id']);
                                            $spec_r = mysqli_query(
                                                $conn,
                                                "SELECT s.id, s.doctor_speciality
                                           FROM speciality s
                                     INNER JOIN doctor_speciality ds ON ds.speciality_id = s.id
                                          WHERE ds.doctor_id = '$doc_id'
                                          ORDER BY s.doctor_speciality"
                                            );
                                            while ($row = mysqli_fetch_assoc($spec_r)) {
                                                echo "<option value='{$row['id']}'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="note" class="form-label fw-bold">Note (Optional)</label>
                                        <input type="text" name="note" id="note" class="form-control"
                                            placeholder="e.g., annual check-up">
                                    </div>
                                </div>
                                <button type="submit" name="book" class="btn btn-primary mt-3">
                                    <i class="fas fa-calendar-check me-2"></i>Finalize Booking
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>

    <!-- ═══ TESTIMONIALS ═════════════════════════════════════════════════════ -->
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
                    <div class="swiper-slide">
                        <div class="testimonial-card">
                            <p>"Booking my appointment was incredibly easy, and the doctor was top-notch. The entire process was seamless. Highly recommend Medkit for anyone's healthcare needs."</p>
                            <div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=a" class="rounded-circle me-3" alt="Patient">
                                <div>
                                    <h6 class="fw-bold mb-0">Naimur Rahman</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="testimonial-card">
                            <p>"The platform is very user-friendly and intuitive. I found a specialist in minutes and got the compassionate care I needed right away. A wonderful experience."</p>
                            <div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=b" class="rounded-circle me-3" alt="Patient">
                                <div>
                                    <h6 class="fw-bold mb-0">Ruhfayed Sakib</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="testimonial-card">
                            <p>"Finally, a healthcare platform that truly works for the patient. Everything from searching for a doctor to finalizing the booking was simple and fast. Five stars!"</p>
                            <div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=c" class="rounded-circle me-3" alt="Patient">
                                <div>
                                    <h6 class="fw-bold mb-0">Shakil Hossain</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="testimonial-card">
                            <p>"A fantastic service that saved me a lot of time and stress. The quality of care I received was excellent, and the staff was very professional. I will be using Medkit again."</p>
                            <div class="d-flex align-items-center mt-3"><img src="https://i.pravatar.cc/70?u=d" class="rounded-circle me-3" alt="Patient">
                                <div>
                                    <h6 class="fw-bold mb-0">Jane Doe</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-pagination position-relative mt-4"></div>
            </div>
        </div>
    </section>

    <!-- ═══ SERVICES ═════════════════════════════════════════════════════════ -->
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

        <?php if (isset($_GET['doctor_id'])): ?>
            setTimeout(function() {
                var $form = $('#booking-form');
                if ($form.length) {
                    $('html, body').animate({
                        scrollTop: $form.offset().top - 80
                    }, 500);
                }
            }, 150);
        <?php endif; ?>

        // ── 3. Date → available time slots (AJAX) ──────────────────────────────
        $(document).on('change', '#appointment_date', function() {
            var selectedDate = $(this).val();
            var doctorId = parseInt($(this).closest('form').find('input[name="doctor_id"]').val(), 10) || 0;
            if (selectedDate && doctorId > 0) {
                $('#time_id').html('<option value="">Loading…</option>');
                $.ajax({
                    type: 'POST',
                    url: 'get_data.php',
                    data: {
                        action: 'get_times',
                        doctor_id: doctorId,
                        selected_date: selectedDate
                    },
                    success: function(response) {
                        $('#time_id').html(response);
                    },
                    error: function() {
                        $('#time_id').html('<option value="">Error loading times</option>');
                    }
                });
            } else {
                $('#time_id').html('<option value="">-- Select a Date first --</option>');
            }
        });

        // ── 4. Search form submit (AJAX, no refresh) ───────────────────────────
        $('#doctor-search-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var originalBtnHtml = $btn.html();

            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Searching...');

            $.ajax({
                type: 'POST',
                url: 'get_data.php',
                data: $form.serialize() + '&action=search_doctors',
                success: function(response) {
                    var $results = $('#search-results');
                    $results.html(response).fadeIn(150);

                    $('html, body').animate({
                        scrollTop: $results.offset().top - 80
                    }, 500);
                },
                error: function() {
                    var $results = $('#search-results');
                    $results.html(
                        '<div class="card text-center py-4 mt-4"><div class="card-body"><h5 class="fw-bold text-danger">Search failed</h5><p class="text-muted mb-0">Please try again.</p></div></div>'
                    ).fadeIn(150);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        });

        // ── 5. Book Now click → load booking form without page refresh ─────────
        $(document).on('click', '.book-now-btn', function(e) {
            e.preventDefault();
            var doctorId = parseInt($(this).data('doctor-id'), 10) || 0;
            if (doctorId <= 0) {
                alert('Invalid doctor selected. Please try again.');
                return;
            }

            $.ajax({
                type: 'POST',
                url: 'get_data.php',
                data: {
                    action: 'get_booking_form',
                    doctor_id: doctorId
                },
                success: function(response) {
                    $('#booking-form-container').html(response);
                    var $form = $('#booking-form');
                    if ($form.length) {
                        $('html, body').animate({
                            scrollTop: $form.offset().top - 80
                        }, 500);
                    }
                },
                error: function() {
                    alert('Unable to load booking form. Please try again.');
                }
            });
        });

        // Allow search only via button click (not Enter key submit)
        $('#doctor-search-form input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // ── 6. Category tile click → populate filters only (no auto-search) ───
        $('.category-item').on('click', function(e) {
            e.preventDefault();
            var categoryName = $(this).data('category-name');
            var categoryId = $(this).data('category-id');

            // Set values
            $('#search_term').val(categoryName);
            $('#category_id_filter').val(categoryId);
            $('#speciality_id_filter').val('');

            // Show speciality dropdown and fetch its options
            $('#speciality-filter-group').fadeIn(200);
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

            // Auto-search immediately, same as clicking the Search button
            $('#doctor-search-form').trigger('submit');

        });

        // ── 7. Clear category filter when user manually clears the text box ─────
        $('#search_term').on('input', function() {
            var val = $(this).val().trim();
            // If user typed something not matching a category tile, clear category context
            if (val === '') {
                $('#category_id_filter').val('0');
                $('#speciality_id_filter').val('');
                $('#speciality-filter-group').fadeOut(200);
            }
        });

        // ── 8. Hover to switch department tabs ───────────────────────────────────
        $('.departments-nav .nav-link').on('mouseenter', function() {
            var tab = new bootstrap.Tab(this);
            tab.show();
        });

    });
</script>