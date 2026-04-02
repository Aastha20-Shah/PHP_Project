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

        // Prevent booking past dates/times
        $appointment_date_clean = trim((string)($_POST['appointment_date'] ?? ''));
        $appointment_day_start = strtotime($appointment_date_clean . ' 00:00:00');
        $today_date = date('Y-m-d');
        $today_start = strtotime($today_date . ' 00:00:00');
        if ($appointment_day_start === false || $today_start === false || $appointment_day_start < $today_start) {
            $_SESSION['message'] = 'Please choose a current or future date.';
            $_SESSION['message_type'] = 'warning';
            header("Location: index.php");
            exit();
        }

        $day_of_week = (int)date('N', $appointment_day_start);
        $time_stmt = $conn->prepare("SELECT dat.start_time FROM doctor_available_time dat INNER JOIN doctor_available_day dad ON dat.day_id = dad.id WHERE dat.id = ? AND dad.doctor_id = ? AND dad.day = ? LIMIT 1");
        $slot_start_time = '';
        if ($time_stmt) {
            $time_stmt->bind_param('iii', $time_id, $doctor_id_post, $day_of_week);
            $time_stmt->execute();
            $time_row = $time_stmt->get_result()->fetch_assoc();
            $slot_start_time = (string)($time_row['start_time'] ?? '');
            $time_stmt->close();
        }

        if ($slot_start_time === '') {
            $_SESSION['message'] = 'Invalid time slot. Please select again.';
            $_SESSION['message_type'] = 'warning';
            header("Location: index.php");
            exit();
        }

        if ($appointment_date_clean === $today_date) {
            $slot_start_ts = strtotime($appointment_date_clean . ' ' . $slot_start_time);
            if ($slot_start_ts !== false && $slot_start_ts <= time()) {
                $_SESSION['message'] = 'This time slot is no longer available. Please choose another.';
                $_SESSION['message_type'] = 'warning';
                header("Location: index.php");
                exit();
            }
        }

        $check = mysqli_query(
            $conn,
            "SELECT id FROM visit_booking
              WHERE doctor_id        = '$doctor_id_post'
                AND time_id          = '$time_id'
                AND appointment_date = '$appointment_date'
                                AND status IN ('pending','accepted','visited')"
        );

        if (mysqli_num_rows($check) > 0) {
            $_SESSION['message']      = "This time slot has already been booked!";
            $_SESSION['message_type'] = "warning";
        } else {
            $ins = "INSERT INTO visit_booking
                        (patient_id, doctor_id, speciality_id, time_id, Note, appointment_date, status, patient_notified)
                    VALUES
                        ('$patient_id','$doctor_id_post','$speciality_id','$time_id','$note','$appointment_date','accepted',1)";
            if (mysqli_query($conn, $ins)) {
                $_SESSION['message']      = "Slot booked successfully.";
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
                case 'pending':
                case 'accepted':
                    $msg      = "Your appointment for <strong>$date</strong> is booked.";
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
    $location_term        = isset($_POST['location_term'])        ? mysqli_real_escape_string($conn, trim($_POST['location_term'])) : '';
    $category_id_filter   = isset($_POST['category_id_filter'])   ? intval($_POST['category_id_filter'])   : 0;
    $speciality_id_filter = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;

    $where_clauses = [];

    // Free-text search across name, category, speciality
    if ($search_term !== '') {
        $where_clauses[] = "(u.firstname  LIKE '%$search_term%'
                          OR u.lastname   LIKE '%$search_term%'
                          OR u.clinic_name LIKE '%$search_term%'
                          OR c.category_name LIKE '%$search_term%'
                          OR s.doctor_speciality LIKE '%$search_term%'
                          OR u.address LIKE '%$search_term%')";
    }

    if ($location_term !== '') {
        $where_clauses[] = "u.address LIKE '%$location_term%'";
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
                            <div class="col-lg-4">
                                <label for="location_term" class="form-label fw-bold small">Location</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-map-marker-alt text-primary"></i></span>
                                    <input type="text" name="location_term" id="location_term" class="form-control border-start-0"
                                        placeholder="e.g., Bangalore"
                                        value="<?php echo isset($_POST['location_term']) ? htmlspecialchars($_POST['location_term']) : ''; ?>">
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <label for="search_term" class="form-label fw-bold small">Doctor / Clinic / Speciality</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-primary"></i></span>
                                    <input type="text" name="search_term" id="search_term" class="form-control border-start-0"
                                        placeholder="e.g., Dr. Smith, Dental, Cardiology"
                                        value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : ''; ?>">
                                </div>
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
                $category_photo_map = [
                    'cardiology'       => 'https://images.unsplash.com/photo-1551190822-a9333d879b1f?auto=format&fit=crop&w=900&q=80',
                    'dentistry'        => 'https://plus.unsplash.com/premium_photo-1682097288491-7e926a30cd0b?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MjF8fGRlbnRpc3R8ZW58MHx8MHx8fDA%3D',
                    'dermatology'      => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=900&q=80',
                    'general medicine' => 'https://images.unsplash.com/photo-1584515933487-779824d29309?auto=format&fit=crop&w=900&q=80',
                    'gynecology'       => 'https://plus.unsplash.com/premium_photo-1661606400554-a2055d50ee08?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8Z3luZWNvbG9naXN0fGVufDB8fDB8fHww',
                    'neurology'        => 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?auto=format&fit=crop&w=900&q=80',
                    'oncology'         => 'https://media.istockphoto.com/id/1496004272/photo/indian-daughter-visit-her-elderly-mother-cancer-patient-undergoing-course-of-chemotherapy-in.webp?a=1&b=1&s=612x612&w=0&k=20&c=_q06t_lCxakr5eM7mvkylFtLK55fXS4QEtiGCzYgxoc=',
                    'pediatric'        => 'https://media.istockphoto.com/id/508509000/photo/professional-pediatrician-examining-infant.webp?a=1&b=1&s=612x612&w=0&k=20&c=oBlnu93leoqIBf_oV4jysrjLqQ1IYfHQfwMQoNDz9bA=',
                    'ortho'            => 'https://plus.unsplash.com/premium_photo-1661436735845-d136f5778f14?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NXx8b3J0aG9wZWRpY3N8ZW58MHx8MHx8fDA%3D',
                    'physio'           => 'https://images.unsplash.com/photo-1580281657521-72f9e8f5f2a8?auto=format&fit=crop&w=900&q=80',
                    'eye'              => 'https://images.unsplash.com/photo-1584982751601-97dcc096659c?auto=format&fit=crop&w=900&q=80',
                    'ent'              => 'https://media.istockphoto.com/id/649286422/photo/happy-little-boy-having-ear-exam.webp?a=1&b=1&s=612x612&w=0&k=20&c=oFFjx78OvcRtyQiMy-G6GcYkp1yieHMOhhBicAyJnwA=',
                    'psychiatry'       => 'https://plus.unsplash.com/premium_photo-1664378616928-dc6842677183?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MXx8cHN5Y2hvbG9naXN0fGVufDB8fDB8fHww',
                    'ophthalmology'    => 'https://plus.unsplash.com/premium_photo-1677333508720-c37038cbf8be?w=600&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NXx8b3BodGhhbG1vbG9naXN0fGVufDB8fDB8fHww',
                ];
                $category_desc_map = [
                    'cardiology'       => 'Heart and blood vessel care by experienced specialists.',
                    'dentistry'        => 'Complete oral and dental care for healthy smiles.',
                    'dermatology'      => 'Skin, hair, and nail treatment from skin experts.',
                    'general medicine' => 'Primary care for everyday health concerns.',
                    'gynecology'       => 'Comprehensive women health and pregnancy care.',
                    'neurology'        => 'Advanced brain, nerve, and spine treatment.',
                    'oncology'         => 'Cancer screening, diagnosis, and specialist support.',
                    'pediatric'        => 'Child-friendly healthcare from infant to teen.',
                    'ortho'            => 'Bone, joint, and muscle pain management.',
                    'physio'           => 'Rehabilitation and movement therapy support.',
                    'eye'              => 'Vision checkups and complete eye treatment.',
                    'ent'              => 'Ear, nose, and throat specialist consultation.',
                    'psychiatry'       => 'Mental health support and counseling care.',
                    'ophthalmology'    => 'Specialized eye surgery and vision care.'
                ];
                $default_category_desc = 'Consult experienced doctors for diagnosis and treatment.';
                $default_category_photo = 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=900&q=80';
                if ($categories && mysqli_num_rows($categories) > 0) {
                    mysqli_data_seek($categories, 0);
                    while ($cat = mysqli_fetch_assoc($categories)):
                        $name_lower = strtolower($cat['category_name']);
                        $category_photo = $default_category_photo;
                        $category_desc = $default_category_desc;
                        foreach ($category_photo_map as $keyword => $photo_url) {
                            if (strpos($name_lower, $keyword) !== false) {
                                $category_photo = $photo_url;
                                $category_desc = $category_desc_map[$keyword] ?? $default_category_desc;
                                break;
                            }
                        }
                ?>
                        <div class="col-lg-3 col-md-4 col-6 mb-4">
                            <a href="#" class="text-decoration-none category-item"
                                data-category-name="<?php echo htmlspecialchars($cat['category_name']); ?>"
                                data-category-id="<?php echo $cat['id']; ?>">
                                <div class="card feature-card h-100 p-3">
                                    <img src="<?php echo htmlspecialchars($category_photo); ?>" class="category-photo mb-3" alt="<?php echo htmlspecialchars($cat['category_name']); ?>">
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($cat['category_name']); ?></h6>
                                    <p class="category-desc text-muted mb-0"><?php echo htmlspecialchars($category_desc); ?></p>
                                </div>
                            </a>
                        </div>
                <?php endwhile;
                } ?>
            </div><!-- /category-browse -->

            <!-- ── FILTER BAR ─────────────── -->
            <div id="filter-bar-container" style="display: none; background: #2b3074; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <div class="row align-items-center g-2" id="filter-bar">
                    <div class="col-md-3">
                        <select name="gender_filter" class="form-select form-select-sm border-0" style="background:#4b5094; color:white;">
                            <option value="">Gender</option>
                            <option value="Male">Male Doctor</option>
                            <option value="Female">Female Doctor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="experience_filter" class="form-select form-select-sm border-0" style="background:#4b5094; color:white;">
                            <option value="0">Experience</option>
                            <option value="5">5+ Years of experience</option>
                            <option value="10">10+ Years of experience</option>
                            <option value="15">15+ Years of experience</option>
                            <option value="20">20+ Years of experience</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-white fw-bold d-flex align-items-center">
                        <span class="me-2">All Filters <i class="fas fa-chevron-down ms-1"></i></span>
                    </div>
                    <div class="col-md-3 d-flex align-items-center">
                        <span class="text-white me-2 fw-bold text-nowrap">Sort By</span>
                        <select name="sort_by" class="form-select form-select-sm border-0" style="background:#4b5094; color:white;">
                            <option value="relevance">Relevance</option>
                            <option value="experience">Experience - High to Low</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── RESULTS (loaded via AJAX only on Search click) ─────────────── -->
            <div id="search-results" style="display:none;"></div>

            <!-- ── BOOKING FORM PLACEHOLDER ─────────── -->
            <div id="booking-form" class="mt-4">
                <div id="booking-form-container"></div>
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
        var bookingLoader = '<div class="slot-message text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading booking form...</div>';

        function injectBookingForm($container, doctorId, options) {
            if (!$container.length || !doctorId) {
                return;
            }
            options = options || {};

            if ($container.data('loaded') === true && options.allowToggle) {
                $container.slideToggle(300);
                return;
            }

            $container
                .stop(true, true)
                .slideDown(0)
                .html(bookingLoader)
                .data('loaded', 'loading');

            $.ajax({
                type: 'POST',
                url: 'get_data.php',
                data: {
                    action: 'get_booking_form',
                    doctor_id: doctorId
                },
                success: function(response) {
                    $container
                        .data('loaded', true)
                        .hide()
                        .html(response)
                        .slideDown(300);

                    if (options.scrollTo) {
                        $('html, body').animate({
                            scrollTop: $container.offset().top - 80
                        }, 500);
                    }
                },
                error: function() {
                    $container
                        .data('loaded', false)
                        .html('<div class="alert alert-danger mb-0">Unable to load booking form. Please try again.</div>');
                }
            });
        }

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
                data: $form.serialize() + '&' + $('#filter-bar select').serialize() + '&action=search_doctors',
                success: function(response) {
                    var $results = $('#search-results');
                    $results.html(response).fadeIn(150);
                    $('#filter-bar-container').fadeIn(150);

                    $('html, body').animate({
                        scrollTop: $('#filter-bar-container').offset().top - 80
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

            var $inlineContainer = $('#inline-booking-' + doctorId);

            if ($inlineContainer.data('loaded') === true) {
                $inlineContainer.slideToggle(300);
                return;
            }

            injectBookingForm($inlineContainer, doctorId, {
                scrollTo: true
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

        // Trigger search on filter change
        $('#filter-bar select').on('change', function() {
            $('#doctor-search-form').submit();
        });

        // Contact Clinic Modal population
        $(document).on('click', '.btn-contact-clinic', function() {
            var phone = $(this).data('phone') || 'Not Available';
            var docName = $(this).data('docname') || '';
            $('#contactClinicPhone').text(phone);
            $('#contactClinicTitle').text('Contact Clinic - Dr. ' + docName);
        });

        var queryDoctorId = <?php echo isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 'null'; ?>;
        if (queryDoctorId) {
            injectBookingForm($('#booking-form-container'), queryDoctorId, {
                scrollTo: true
            });
        } else {
            $('#booking-form-container').html('<div class="slot-placeholder text-center text-muted py-4">Select a doctor to view available slots.</div>');
        }

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