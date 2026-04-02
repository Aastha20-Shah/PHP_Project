<?php
session_start();
include('config.php');
include('profile_image_helpers.php');

medikit_ensure_profile_image_schema($conn);

if (!function_exists('medikit_fetch_slots_for_date')) {
    function medikit_fetch_slots_for_date($conn, $doctor_id, $selected_date)
    {
        $selected_date = trim($selected_date ?? '');
        if ($selected_date === '') {
            return [
                'status' => 'invalid',
                'message' => 'Please choose a valid date.',
                'total' => 0,
                'periods' => []
            ];
        }

        $timestamp = strtotime($selected_date);
        if ($timestamp === false) {
            return [
                'status' => 'invalid',
                'message' => 'Unable to read the selected date.',
                'total' => 0,
                'periods' => []
            ];
        }

        // Prevent showing slots for past dates
        $today_date = date('Y-m-d');
        $selected_day_start = strtotime($selected_date . ' 00:00:00');
        $today_start = strtotime($today_date . ' 00:00:00');
        if ($selected_day_start !== false && $today_start !== false && $selected_day_start < $today_start) {
            return [
                'status' => 'past_date',
                'message' => 'Please choose a current or future date.',
                'total' => 0,
                'periods' => []
            ];
        }

        $day_of_week = intval(date('N', $timestamp));

        $day_stmt = $conn->prepare("SELECT id FROM doctor_available_day WHERE doctor_id = ? AND day = ?");
        $day_stmt->bind_param("ii", $doctor_id, $day_of_week);
        $day_stmt->execute();
        $day_result = $day_stmt->get_result();

        if ($day_result->num_rows === 0) {
            return [
                'status' => 'not_available',
                'message' => 'Not available on this day.',
                'total' => 0,
                'periods' => []
            ];
        }

        $day_id = intval($day_result->fetch_assoc()['id']);
        $booked_slots = [];

        $booked_stmt = $conn->prepare("
            SELECT time_id FROM visit_booking
            WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','accepted','visited')
        ");
        $booked_stmt->bind_param("is", $doctor_id, $selected_date);
        $booked_stmt->execute();
        $booked_result = $booked_stmt->get_result();
        while ($row = $booked_result->fetch_assoc()) {
            $booked_slots[] = intval($row['time_id']);
        }

        $time_stmt = $conn->prepare("
            SELECT id, start_time, end_time
            FROM doctor_available_time
            WHERE day_id = ?
            ORDER BY start_time
        ");
        $time_stmt->bind_param("i", $day_id);
        $time_stmt->execute();
        $time_result = $time_stmt->get_result();

        if ($time_result->num_rows === 0) {
            return [
                'status' => 'not_configured',
                'message' => 'No time slots configured.',
                'total' => 0,
                'periods' => []
            ];
        }

        $periods = [
            'morning' => [],
            'afternoon' => [],
            'evening' => []
        ];

        $is_today = ($selected_date === $today_date);
        $now_ts = time();
        $unbooked_count = 0;
        $past_unbooked_count = 0;

        while ($row = $time_result->fetch_assoc()) {
            $time_id = intval($row['id']);
            if (in_array($time_id, $booked_slots, true)) {
                continue;
            }

            $unbooked_count++;

            // If selecting today's date, hide any time slots that already passed
            if ($is_today) {
                $slot_start_ts = strtotime($selected_date . ' ' . $row['start_time']);
                if ($slot_start_ts !== false && $slot_start_ts <= $now_ts) {
                    $past_unbooked_count++;
                    continue;
                }
            }

            $hour = intval(date('H', strtotime($row['start_time'])));
            $start_label = date("h:i A", strtotime($row['start_time']));
            $range_label = date("h:i A", strtotime($row['start_time'])) . " - " . date("h:i A", strtotime($row['end_time']));
            $slot = [
                'id' => $time_id,
                'label' => $start_label,
                'range' => $range_label
            ];

            if ($hour < 12) {
                $periods['morning'][] = $slot;
            } elseif ($hour < 17) {
                $periods['afternoon'][] = $slot;
            } else {
                $periods['evening'][] = $slot;
            }
        }

        $total = count($periods['morning']) + count($periods['afternoon']) + count($periods['evening']);

        if ($total === 0) {
            if ($is_today && $unbooked_count > 0 && $past_unbooked_count === $unbooked_count) {
                return [
                    'status' => 'fully_booked',
                    'message' => 'No slots available for today.',
                    'total' => 0,
                    'periods' => []
                ];
            }
            return [
                'status' => 'fully_booked',
                'message' => 'All slots are booked for this date.',
                'total' => 0,
                'periods' => []
            ];
        }

        return [
            'status' => 'ok',
            'message' => '',
            'total' => $total,
            'periods' => $periods
        ];
    }
}

// Action to get specialities for a category
if (isset($_POST['action']) && $_POST['action'] == 'get_specialities_for_search') {
    $cat_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    if ($cat_id > 0) {
        $stmt = $conn->prepare("SELECT id, doctor_speciality FROM speciality WHERE category_id = ? ORDER BY doctor_speciality");
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $options = '<option value="">-- All Specialities --</option>';
        while ($row = $result->fetch_assoc()) {
            $options .= "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
        }
        echo $options;
    } else {
        echo '<option value="">-- Select Category First --</option>';
    }
    exit;
}

// Action to get available time slots for a doctor on a specific date
if (isset($_POST['action']) && $_POST['action'] == 'get_times') {
    $doctor_id = intval($_POST['doctor_id']);
    $selected_date = $_POST['selected_date'];

    $slots = medikit_fetch_slots_for_date($conn, $doctor_id, $selected_date);

    if ($slots['status'] !== 'ok') {
        $message = htmlspecialchars($slots['message'] ?: 'No slots available.');
        echo '<div class="slot-message text-center">' . $message . '</div>';
        exit;
    }

    $period_icons = [
        'morning' => '<i class="fas fa-sun me-2 text-warning"></i>Morning',
        'afternoon' => '<i class="fas fa-cloud-sun me-2 text-primary"></i>Afternoon',
        'evening' => '<i class="fas fa-moon me-2 text-info"></i>Evening'
    ];

    $output = '<div class="slot-grid">';
    foreach ($slots['periods'] as $period => $items) {
        if (empty($items)) {
            continue;
        }
        $title = $period_icons[$period] ?? ucfirst($period);
        $slot_count = count($items);
        $output .= '<div class="slot-period">';
        $output .= '  <div class="slot-period-title">' . $title . ' <span class="slot-period-count">(' . $slot_count . ' slots)</span></div>';
        $output .= '  <div class="slot-period-body">';
        foreach ($items as $slot) {
            $label = htmlspecialchars($slot['label']);
            $range = htmlspecialchars($slot['range']);
            $output .= "<button type='button' class='slot-btn' data-time-id='{$slot['id']}' title='{$range}'>{$label}</button>";
        }
        $output .= '  </div>';
        $output .= '</div>';
    }
    $output .= '</div>';

    echo $output;
    exit;
}

// Action to search doctors and return rendered HTML results
if (isset($_POST['action']) && $_POST['action'] == 'search_doctors') {
    $search_term = isset($_POST['search_term']) ? mysqli_real_escape_string($conn, trim($_POST['search_term'])) : '';
    $location_term = isset($_POST['location_term']) ? mysqli_real_escape_string($conn, trim($_POST['location_term'])) : '';
    $category_id_filter = isset($_POST['category_id_filter']) ? intval($_POST['category_id_filter']) : 0;
    $speciality_id_filter = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;

    $gender_filter = isset($_POST['gender_filter']) ? mysqli_real_escape_string($conn, trim($_POST['gender_filter'])) : '';
    $experience_filter = isset($_POST['experience_filter']) ? intval($_POST['experience_filter']) : 0;
    $sort_by = isset($_POST['sort_by']) ? mysqli_real_escape_string($conn, trim($_POST['sort_by'])) : 'relevance';

    $where_clauses = [];

    if ($search_term !== '') {
        $where_clauses[] = "(u.firstname LIKE '%$search_term%'
                          OR u.lastname LIKE '%$search_term%'
                          OR u.clinic_name LIKE '%$search_term%'
                          OR c.category_name LIKE '%$search_term%'
                          OR s.doctor_speciality LIKE '%$search_term%'
                          OR u.address LIKE '%$search_term%')";
    }

    if ($location_term !== '') {
        $where_clauses[] = "u.address LIKE '%$location_term%'";
    }

    if ($category_id_filter > 0) {
        $where_clauses[] = "c.id = '$category_id_filter'";
    }

    if ($speciality_id_filter > 0) {
        $where_clauses[] = "EXISTS (
            SELECT 1 FROM doctor_speciality ds_sub
             WHERE ds_sub.doctor_id = u.id
               AND ds_sub.speciality_id = '$speciality_id_filter'
        )";
    }

    if ($gender_filter !== '') {
        $where_clauses[] = "u.gender = '$gender_filter'";
    }

    if ($experience_filter > 0) {
        $where_clauses[] = "u.experience_years >= $experience_filter";
    }

    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $order_sql = "ORDER BY u.firstname, u.lastname";
    if ($sort_by === 'experience') {
        $order_sql = "ORDER BY u.experience_years DESC, u.firstname";
    }

    $query = "SELECT u.id, u.firstname, u.lastname, u.address, u.phone_number, u.experience_years, c.category_name,
                     MAX(u.profile_image) AS profile_image,
                     GROUP_CONCAT(DISTINCT s.doctor_speciality SEPARATOR ', ') AS doctor_specialities
              FROM users u
              INNER JOIN category c ON u.category_id = c.id
              INNER JOIN doctor_speciality ds ON ds.doctor_id = u.id
              INNER JOIN speciality s ON s.id = ds.speciality_id
              $where_sql
              GROUP BY u.id
              $order_sql";

    $result = mysqli_query($conn, $query);

    if (!$result) {
        echo '<div class="card text-center py-4 mt-4"><div class="card-body"><h5 class="fw-bold text-danger">Search error</h5><p class="text-muted mb-0">' . htmlspecialchars(mysqli_error($conn)) . '</p></div></div>';
        exit;
    }

    $doctors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $doctors[] = $row;
    }

    if (!empty($doctors)) {
        echo '<h4 class="fw-bold my-4 text-center">Search Results <span class="badge bg-primary ms-2">' . count($doctors) . '</span></h4>';
        foreach ($doctors as $doc) {
            $initials = strtoupper(substr($doc['firstname'], 0, 1) . substr($doc['lastname'], 0, 1));
            $name = htmlspecialchars($doc['firstname'] . ' ' . $doc['lastname']);
            $specialities = htmlspecialchars($doc['doctor_specialities']);
            $category = htmlspecialchars($doc['category_name']);
            $address = htmlspecialchars($doc['address']);
            $experience = intval($doc['experience_years']);
            $phone = htmlspecialchars($doc['phone_number']);
            $doctor_id = intval($doc['id']);

            $profile_image = (string)($doc['profile_image'] ?? '');
            $profile_image = str_replace('\\', '/', $profile_image);
            $profile_image = ltrim($profile_image, '/');
            $has_photo = ($profile_image !== ''
                && strpos($profile_image, 'uploads/doctors/') === 0
                && file_exists(__DIR__ . '/' . $profile_image));

            $avatar_inner = $has_photo
                ? '<img src="' . htmlspecialchars($profile_image) . '" alt="Dr. ' . $name . '" style="width:100%;height:100%;object-fit:cover;display:block;">'
                : $initials;

            echo '<div class="card mb-3 shadow-sm doctor-card" style="border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden;">';
            echo '  <div class="card-body p-4">';
            echo '    <div class="row align-items-center">';
            echo '      <div class="col-md-2 text-center mb-3 mb-md-0">';
            echo '        <div class="doctor-avatar rounded-circle d-flex align-items-center justify-content-center mx-auto overflow-hidden" style="width: 80px; height: 80px; font-size: 28px; background: #e8f0fe; color: #1a76d1;">' . $avatar_inner . '</div>';
            echo '      </div>';
            echo '      <div class="col-md-6 border-end">';
            echo '        <h5 class="fw-bold mb-1"><a href="doctor_details.php?doctor_id=' . $doctor_id . '" class="text-primary text-decoration-none">Dr. ' . $name . '</a></h5>';
            echo '        <p class="text-muted fw-bold mb-1">' . $specialities . '</p>';
            echo '        <p class="text-muted small mb-1">' . $experience . ' years experience overall</p>';
            echo '        <p class="text-muted small mb-2"><i class="fas fa-map-marker-alt me-1 text-primary"></i>' . $address . '</p>';
            echo '        <p class="text-muted small mb-0"><i class="fas fa-money-bill-wave me-1 text-success"></i>Pay at Clinic</p>';
            echo '      </div>';
            echo '      <div class="col-md-4 text-md-center mt-3 mt-md-0">';
            echo '        <button class="btn btn-primary btn-sm w-100 mb-2 py-2 fw-bold book-now-btn" data-doctor-id="' . $doctor_id . '" style="border-radius: 6px;"><i class="fas fa-calendar-check me-2"></i>Book Clinic Visit</button>';
            echo '        <button class="btn btn-outline-primary btn-sm w-100 py-2 fw-bold btn-contact-clinic" data-bs-toggle="modal" data-bs-target="#contactClinicModal" data-phone="' . $phone . '" data-docname="' . $name . '" style="border-radius: 6px;"><i class="fas fa-phone-alt me-2"></i>Contact Clinic</button>';
            echo '      </div>';
            echo '    </div>';
            echo '    <div class="booking-inline-container mt-4" id="inline-booking-' . $doctor_id . '" style="display:none; background:#f8f9fa; padding:20px; border-radius:8px; border-top: 1px solid #e2e8f0;"></div>';
            echo '  </div>';
            echo '</div>';
        }
    } else {
        echo '<div class="card text-center py-5 mt-4"><div class="card-body"><i class="fas fa-user-doctor fs-1 text-warning mb-3"></i><h4 class="fw-bold">No Doctors Found</h4><p class="text-muted">Please try a different search term or category.</p></div></div>';
    }

    exit;
}

// Action to load booking form HTML for a specific doctor
if (isset($_POST['action']) && $_POST['action'] == 'get_booking_form') {
    $doctor_id = isset($_POST['doctor_id']) ? intval($_POST['doctor_id']) : 0;
    $layout = isset($_POST['layout']) ? trim($_POST['layout']) : 'default';

    if ($doctor_id <= 0) {
        echo '<div class="alert alert-danger mb-0"><p class="mb-0">Invalid doctor selected.</p></div>';
        exit;
    }

    $doc_stmt = $conn->prepare("
        SELECT firstname, lastname, address, phone_number, experience_years, clinic_name, profile_image
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $doc_stmt->bind_param("i", $doctor_id);
    $doc_stmt->execute();
    $doctor = $doc_stmt->get_result()->fetch_assoc();

    if (!$doctor) {
        echo '<div class="alert alert-danger mb-0"><p class="mb-0">Doctor not found.</p></div>';
        exit;
    }

    $doctor['profile_image'] = $doctor['profile_image'] ?? '';

    $spec_r = mysqli_query(
        $conn,
        "SELECT s.id, s.doctor_speciality
           FROM speciality s
     INNER JOIN doctor_speciality ds ON ds.speciality_id = s.id
          WHERE ds.doctor_id = '$doctor_id'
          ORDER BY s.doctor_speciality"
    );

    if (!$spec_r) {
        echo '<div class="alert alert-danger mb-0"><p class="mb-0">Unable to load booking form.</p></div>';
        exit;
    }

    $is_logged_in = isset($_SESSION['patient_id']);
    $initials = strtoupper(substr($doctor['firstname'], 0, 1) . substr($doctor['lastname'], 0, 1));

    $profile_image = (string)($doctor['profile_image'] ?? '');
    $profile_image = str_replace('\\', '/', $profile_image);
    $profile_image = ltrim($profile_image, '/');
    $has_photo = ($profile_image !== ''
        && strpos($profile_image, 'uploads/doctors/') === 0
        && file_exists(__DIR__ . '/' . $profile_image));

    $day_cards = [];
    for ($i = 0; $i < 7; $i++) {
        $timestamp = strtotime("+$i days");
        $date_val = date('Y-m-d', $timestamp);
        $display_day = ($i == 0) ? "Today" : (($i == 1) ? "Tomorrow" : date('D, j M', $timestamp));
        $slots_info = medikit_fetch_slots_for_date($conn, $doctor_id, $date_val);
        $slot_label = ($slots_info['total'] > 0)
            ? $slots_info['total'] . ' Slots Available'
            : (($slots_info['status'] === 'not_available') ? 'Not Available' : 'No Slots');

        $day_cards[] = [
            'date' => $date_val,
            'label' => $display_day,
            'availability' => $slot_label
        ];
    }
    $is_sidebar_layout = ($layout === 'sidebar');
    ob_start();
?>
    <div class="slot-booking-card shadow-sm <?php echo $is_sidebar_layout ? 'slot-booking-card-sidebar' : ''; ?>" id="booking-form-<?php echo $doctor_id; ?>">
        <?php if (!$is_sidebar_layout): ?>
            <div class="slot-card-header d-flex flex-wrap align-items-center">
                <div class="slot-avatar me-3" style="overflow:hidden;">
                    <?php if ($has_photo): ?>
                        <img src="<?php echo htmlspecialchars($profile_image); ?>" alt="Dr. <?php echo htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']); ?>" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:50%;">
                    <?php else: ?>
                        <?php echo htmlspecialchars($initials); ?>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <h5 class="mb-1">Dr. <?php echo htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']); ?></h5>
                    <div class="text-muted small"><?php echo intval($doctor['experience_years']); ?> years experience overall</div>
                    <div class="text-muted small"><i class="fas fa-map-marker-alt me-1 text-primary"></i><?php echo htmlspecialchars($doctor['address'] ?: 'Clinic location to be confirmed'); ?></div>
                </div>
                <div class="text-end">
                    <div class="slot-card-fee text-primary fw-bold">Pay at Clinic</div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2 btn-contact-clinic" data-bs-toggle="modal" data-bs-target="#contactClinicModal" data-phone="<?php echo htmlspecialchars($doctor['phone_number']); ?>" data-docname="<?php echo htmlspecialchars($doctor['firstname'] . ' ' . $doctor['lastname']); ?>"><i class="fas fa-phone-alt me-1"></i>Contact Clinic</button>
                </div>
            </div>
        <?php else: ?>
            <div class="slot-side-title mb-3">Pick a time slot</div>
            <div class="slot-side-visit mb-3 d-flex justify-content-between align-items-center">
                <div><i class="fas fa-house-user me-2"></i>Clinic Appointment</div>
                <strong>₹ 300 fee</strong>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" id="booking-form-<?php echo $doctor_id; ?>-form" class="slot-form">
            <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
            <input type="hidden" name="appointment_date" id="appt_date_<?php echo $doctor_id; ?>" value="">
            <input type="hidden" name="time_id" id="time_slot_<?php echo $doctor_id; ?>" value="">

            <div class="slot-day-pills <?php echo $is_sidebar_layout ? 'slot-day-pills-sidebar' : ''; ?>" id="days_<?php echo $doctor_id; ?>">
                <?php foreach ($day_cards as $card): ?>
                    <button type="button" class="slot-day-pill" data-date="<?php echo htmlspecialchars($card['date']); ?>" data-doc="<?php echo $doctor_id; ?>">
                        <span class="slot-day-label"><?php echo htmlspecialchars($card['label']); ?></span>
                        <span class="slot-day-meta"><?php echo htmlspecialchars($card['availability']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="slot-body <?php echo $is_sidebar_layout ? 'slot-body-sidebar' : ''; ?>" id="slots_<?php echo $doctor_id; ?>">
                <div class="slot-message text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading slots...</div>
            </div>

            <div class="slot-final" id="final_<?php echo $doctor_id; ?>">
                <div class="slot-summary d-flex flex-wrap align-items-center mb-3">
                    <div class="slot-summary-block me-3">
                        <div class="slot-summary-label">Selected Date</div>
                        <div class="slot-summary-value" id="summary_date_<?php echo $doctor_id; ?>">Choose a day</div>
                    </div>
                    <div class="slot-summary-block">
                        <div class="slot-summary-label">Selected Time</div>
                        <div class="slot-summary-value" id="summary_time_<?php echo $doctor_id; ?>">Pick a slot</div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Reason / Speciality</label>
                        <select name="speciality_id" class="form-select form-select-sm" required>
                            <?php
                            if (mysqli_num_rows($spec_r) > 0) {
                                while ($row = mysqli_fetch_assoc($spec_r)) {
                                    echo "<option value='" . intval($row['id']) . "'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small">Note (Optional)</label>
                        <input type="text" name="note" class="form-control form-control-sm" placeholder="e.g., annual check-up">
                    </div>
                </div>

                <?php if ($is_logged_in): ?>
                    <button type="submit" name="book" class="btn btn-primary w-100 fw-bold mt-3">
                        <i class="fas fa-check-circle me-2"></i>Confirm Booking
                    </button>
                <?php else: ?>
                    <a href="loginpatient.php" class="btn btn-warning w-100 fw-bold mt-3 text-white text-decoration-none">
                        <i class="fas fa-lock me-2"></i>Login to Confirm
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        (function($) {
            var dr = <?php echo $doctor_id; ?>;
            var $dayTabs = $("#days_" + dr + " .slot-day-pill");
            var $slotsWrap = $("#slots_" + dr);
            var $final = $("#final_" + dr);
            var $dateInput = $("#appt_date_" + dr);
            var $timeInput = $("#time_slot_" + dr);
            var $summaryDate = $("#summary_date_" + dr);
            var $summaryTime = $("#summary_time_" + dr);
            var loader = '<div class="slot-message text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading slots...</div>';

            function loadSlots(date) {
                $slotsWrap.html(loader);
                $.post("get_data.php", {
                        action: "get_times",
                        doctor_id: dr,
                        selected_date: date
                    })
                    .done(function(html) {
                        $slotsWrap.html(html);
                    })
                    .fail(function() {
                        $slotsWrap.html('<div class="slot-message text-danger text-center">Unable to load slots.</div>');
                    });
            }

            $dayTabs.on("click", function() {
                var $tab = $(this);
                $dayTabs.removeClass("active");
                $tab.addClass("active");
                var date = $tab.data("date");
                var label = $tab.find(".slot-day-label").text();
                $dateInput.val(date);
                $timeInput.val("");
                $summaryDate.text(label + " (" + date + ")");
                $summaryTime.text("Pick a slot");
                $final.removeClass("show");
                loadSlots(date);
            });

            $slotsWrap.on("click", ".slot-btn", function() {
                var $btn = $(this);
                $slotsWrap.find(".slot-btn").removeClass("selected");
                $btn.addClass("selected");
                $timeInput.val($btn.data("time-id"));
                $summaryTime.text($btn.text());
                $final.addClass("show");
            });

            $("#booking-form-" + dr + "-form").on("submit", function(e) {
                if (!$dateInput.val() || !$timeInput.val()) {
                    e.preventDefault();
                    alert("Please select a date and time slot to continue.");
                }
            });

            $dayTabs.first().trigger("click");
        })(jQuery);
    </script>
<?php
    echo ob_get_clean();
    exit;
}
