<?php
include('config.php');

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
    $day_of_week = date('N', strtotime($selected_date));

    $options = '<option value="">-- Select Time Slot --</option>';

    $day_stmt = $conn->prepare("SELECT id FROM doctor_available_day WHERE doctor_id = ? AND day = ?");
    $day_stmt->bind_param("ii", $doctor_id, $day_of_week);
    $day_stmt->execute();
    $day_result = $day_stmt->get_result();

    if ($day_result->num_rows > 0) {
        $day_id = $day_result->fetch_assoc()['id'];

        $booked_slots = [];

        // --- BUG FIX IS HERE ---
        // We now only check for appointments that are 'accepted' or 'pending' to block a slot.
        // 'visited' and 'rejected' slots are now correctly considered available.
        $booked_stmt = $conn->prepare("
            SELECT time_id FROM visit_booking 
            WHERE doctor_id = ? AND appointment_date = ? AND (status = 'accepted' OR status = 'pending')
        ");
        $booked_stmt->bind_param("is", $doctor_id, $selected_date);
        $booked_stmt->execute();
        $booked_result = $booked_stmt->get_result();
        while ($row = $booked_result->fetch_assoc()) {
            $booked_slots[] = $row['time_id'];
        }

        $time_stmt = $conn->prepare("SELECT id, start_time, end_time FROM doctor_available_time WHERE day_id = ? ORDER BY start_time");
        $time_stmt->bind_param("i", $day_id);
        $time_stmt->execute();
        $time_result = $time_stmt->get_result();

        if ($time_result->num_rows > 0) {
            $slot_found = false;
            while ($row = $time_result->fetch_assoc()) {
                if (!in_array($row['id'], $booked_slots)) {
                    $start = date("g:i A", strtotime($row['start_time']));
                    $end = date("g:i A", strtotime($row['end_time']));
                    $options .= "<option value='{$row['id']}'>{$start} - {$end}</option>";
                    $slot_found = true;
                }
            }
            if (!$slot_found) {
                $options = '<option value="" disabled>All slots are booked for this date</option>';
            }
        } else {
            $options = '<option value="" disabled>No time slots configured</option>';
        }
    } else {
        $options = '<option value="" disabled>Not available on this day</option>';
    }
    echo $options;
    exit;
}

// Action to search doctors and return rendered HTML results
if (isset($_POST['action']) && $_POST['action'] == 'search_doctors') {
    $search_term = isset($_POST['search_term']) ? mysqli_real_escape_string($conn, trim($_POST['search_term'])) : '';
    $category_id_filter = isset($_POST['category_id_filter']) ? intval($_POST['category_id_filter']) : 0;
    $speciality_id_filter = isset($_POST['speciality_id_filter']) ? intval($_POST['speciality_id_filter']) : 0;

    $where_clauses = [];

    if ($search_term !== '') {
        $where_clauses[] = "(u.firstname LIKE '%$search_term%'
                          OR u.lastname LIKE '%$search_term%'
                          OR c.category_name LIKE '%$search_term%'
                          OR s.doctor_speciality LIKE '%$search_term%')";
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

    $where_sql = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $query = "SELECT u.id, u.firstname, u.lastname, u.address, c.category_name,
                     GROUP_CONCAT(DISTINCT s.doctor_speciality SEPARATOR ', ') AS doctor_specialities
              FROM users u
              INNER JOIN category c ON u.category_id = c.id
              INNER JOIN doctor_speciality ds ON ds.doctor_id = u.id
              INNER JOIN speciality s ON s.id = ds.speciality_id
              $where_sql
              GROUP BY u.id
              ORDER BY u.firstname, u.lastname";

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
            $doctor_id = intval($doc['id']);

            echo '<div class="card mb-3 shadow-sm">';
            echo '  <div class="card-body p-3">';
            echo '    <div class="row align-items-center">';
            echo '      <div class="col-md-2 text-center">';
            echo '        <div class="doctor-avatar rounded-circle d-flex align-items-center justify-content-center mx-auto">' . $initials . '</div>';
            echo '      </div>';
            echo '      <div class="col-md-7">';
            echo '        <h5 class="fw-bold mb-1 text-primary">Dr. ' . $name . '</h5>';
            echo '        <p class="text-success fw-bold mb-1"><i class="fas fa-stethoscope me-1"></i>' . $specialities . '</p>';
            echo '        <p class="text-muted small mb-1"><i class="fas fa-tag me-1"></i>' . $category . '</p>';
            echo '        <p class="text-muted small mb-0"><i class="fas fa-map-marker-alt me-1"></i>' . $address . '</p>';
            echo '      </div>';
            echo '      <div class="col-md-3 text-md-end mt-3 mt-md-0">';
            echo '        <a href="#" class="btn btn-primary btn-sm book-now-btn" data-doctor-id="' . $doctor_id . '"><i class="fas fa-calendar-plus me-1"></i>Book Now</a>';
            echo '      </div>';
            echo '    </div>';
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

    if ($doctor_id <= 0) {
        echo '<div class="card mt-4" id="booking-form"><div class="card-body"><p class="text-danger mb-0">Invalid doctor selected.</p></div></div>';
        exit;
    }

    $spec_r = mysqli_query(
        $conn,
        "SELECT s.id, s.doctor_speciality
           FROM speciality s
     INNER JOIN doctor_speciality ds ON ds.speciality_id = s.id
          WHERE ds.doctor_id = '$doctor_id'
          ORDER BY s.doctor_speciality"
    );

    if (!$spec_r) {
        echo '<div class="card mt-4" id="booking-form"><div class="card-body"><p class="text-danger mb-0">Unable to load booking form right now.</p></div></div>';
        exit;
    }

    echo '<div class="card mt-4" id="booking-form">';
    echo '  <div class="card-body p-4">';
    echo '    <h4 class="card-title fw-bold mb-3"><i class="fas fa-calendar-alt me-2 text-primary"></i>Complete Your Booking</h4>';
    echo '    <form method="POST" action="index.php">';
    echo '      <input type="hidden" name="doctor_id" value="' . $doctor_id . '">';
    echo '      <div class="row g-3">';
    echo '        <div class="col-md-6">';
    echo '          <label for="appointment_date" class="form-label fw-bold">Date</label>';
    echo '          <input type="date" name="appointment_date" id="appointment_date" class="form-control" required min="' . date('Y-m-d') . '">';
    echo '        </div>';
    echo '        <div class="col-md-6">';
    echo '          <label for="time_id" class="form-label fw-bold">Time</label>';
    echo '          <select name="time_id" id="time_id" class="form-select" required>';
    echo '            <option value="">-- Select a Date first --</option>';
    echo '          </select>';
    echo '        </div>';
    echo '        <div class="col-md-6">';
    echo '          <label for="speciality_id" class="form-label fw-bold">Reason / Speciality</label>';
    echo '          <select name="speciality_id" id="speciality_id" class="form-select" required>';

    if (mysqli_num_rows($spec_r) > 0) {
        while ($row = mysqli_fetch_assoc($spec_r)) {
            echo "<option value='" . intval($row['id']) . "'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
        }
    } else {
        echo '<option value="" disabled>No speciality available</option>';
    }

    echo '          </select>';
    echo '        </div>';
    echo '        <div class="col-md-6">';
    echo '          <label for="note" class="form-label fw-bold">Note (Optional)</label>';
    echo '          <input type="text" name="note" id="note" class="form-control" placeholder="e.g., annual check-up">';
    echo '        </div>';
    echo '      </div>';
    echo '      <button type="submit" name="book" class="btn btn-primary mt-3"><i class="fas fa-calendar-check me-2"></i>Finalize Booking</button>';
    echo '    </form>';
    echo '  </div>';
    echo '</div>';

    exit;
}
