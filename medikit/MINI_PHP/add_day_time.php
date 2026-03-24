<?php
session_start();
include("config.php");

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$msg = "";
$msg_type = "";

// Days map
$days_map = [
    '1' => 'Monday',
    '2' => 'Tuesday',
    '3' => 'Wednesday',
    '4' => 'Thursday',
    '5' => 'Friday',
    '6' => 'Saturday',
    '7' => 'Sunday'
];

// --------------------------
// Add New Time Slot
// --------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_time'])) {
    $days = $_POST['days'] ?? [];
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);

    $check_q = mysqli_query($conn, "
        SELECT COUNT(t.id) 
        FROM doctor_available_time t
        JOIN doctor_available_day d ON t.day_id = d.id
        WHERE d.doctor_id='$doctor_id' AND t.start_time='$start_time' AND t.end_time='$end_time'
    ");
    $count = mysqli_fetch_row($check_q)[0];

    if ($count > 0) {
        $msg = "This exact time range already exists. Use the rows above to modify days.";
        $msg_type = "error";
    } elseif (!empty($days)) {
        foreach ($days as $day_num) {
            $day_num = (int)$day_num;

            $day_q = mysqli_query($conn, "SELECT id FROM doctor_available_day WHERE doctor_id='$doctor_id' AND day='$day_num'");
            if (mysqli_num_rows($day_q) > 0) {
                $day_id = mysqli_fetch_assoc($day_q)['id'];
            } else {
                mysqli_query($conn, "INSERT INTO doctor_available_day (doctor_id, day) VALUES ('$doctor_id', '$day_num')");
                $day_id = mysqli_insert_id($conn);
            }

            $exist_check_q = mysqli_query($conn, "SELECT id FROM doctor_available_time WHERE day_id='$day_id' AND start_time='$start_time' AND end_time='$end_time'");
            if (mysqli_num_rows($exist_check_q) == 0) {
                mysqli_query($conn, "INSERT INTO doctor_available_time (day_id, start_time, end_time) VALUES ('$day_id','$start_time','$end_time')");
            }
        }
        $msg = "New time slot saved successfully!";
        $msg_type = "success";
    } else {
        $msg = "Please select at least one day and enter time.";
        $msg_type = "error";
    }
}

// --------------------------
// DELETE Time Slot (Handle Delete button click)
// --------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_time_id'])) {
    $time_id = intval($_POST['time_id']);

    // Find the time range associated with this time_id
    $time_q = mysqli_query($conn, "SELECT start_time, end_time FROM doctor_available_time WHERE id='$time_id'");
    if ($time_row = mysqli_fetch_assoc($time_q)) {
        $del_start = mysqli_real_escape_string($conn, $time_row['start_time']);
        $del_end = mysqli_real_escape_string($conn, $time_row['end_time']);

        // Find all day_ids that use this time range for this doctor
        $day_ids_to_check = [];
        $day_q = mysqli_query($conn, "
            SELECT d.id 
            FROM doctor_available_day d
            JOIN doctor_available_time t ON d.id = t.day_id
            WHERE d.doctor_id = '$doctor_id' AND t.start_time = '$del_start' AND t.end_time = '$del_end'
        ");
        while ($d_row = mysqli_fetch_assoc($day_q)) {
            $day_ids_to_check[] = $d_row['id'];
        }

        // Delete all corresponding time slot entries
        mysqli_query($conn, "
            DELETE t 
            FROM doctor_available_time t
            JOIN doctor_available_day d ON t.day_id = d.id
            WHERE d.doctor_id = '$doctor_id' AND t.start_time = '$del_start' AND t.end_time = '$del_end'
        ");

        // Cleanup orphaned doctor_available_day entries
        foreach ($day_ids_to_check as $did) {
            $check_time_q = mysqli_query($conn, "SELECT COUNT(*) FROM doctor_available_time WHERE day_id='$did'");
            $count_t = mysqli_fetch_row($check_time_q)[0];
            if ($count_t == 0) {
                mysqli_query($conn, "DELETE FROM doctor_available_day WHERE id='$did'");
            }
        }
    }
}


// --------------------------
// Fetch Time Slots
// --------------------------
$time_slots = [];
$res = mysqli_query($conn, "
    SELECT t.id as time_id, t.start_time, t.end_time, d.id as day_id, d.day
    FROM doctor_available_time t
    JOIN doctor_available_day d ON t.day_id=d.id
    WHERE d.doctor_id='$doctor_id'
    ORDER BY t.start_time
");

while ($row = mysqli_fetch_assoc($res)) {
    $unique_time_key = $row['start_time'] . '_' . $row['end_time'];
    if (!isset($time_slots[$unique_time_key])) {
        $time_slots[$unique_time_key] = [
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'days' => []
        ];
    }
    $time_slots[$unique_time_key]['days'][$row['day']] = [
        'day_id' => $row['day_id'],
        'time_id' => $row['time_id']
    ];
}

$time_slots = array_values($time_slots);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Availability</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --background-color: #ecf0f1;
            --card-background: #ffffff;
            --text-color: #555;
            --heading-color: var(--secondary-color);
            --border-radius: 12px;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
            --success-color: #27ae60;
            --pending-color: #e67e22;
        }

        body {
            font-family: 'Nunito Sans', sans-serif;
            margin: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: var(--secondary-color);
            color: #fff;
            position: fixed;
            height: 100%;
            padding-top: 25px;
        }

        .sidebar-header {
            text-align: center;
            padding-bottom: 25px;
            font-size: 24px;
            font-weight: 800;
        }

        .sidebar-header i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 30px;
            text-decoration: none;
            color: #bdc3c7;
            font-weight: 600;
            font-size: 16px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .sidebar-nav a:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .sidebar-nav a.active {
            background-color: var(--primary-color);
            color: #fff;
            border-left: 4px solid #fff;
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 30px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: var(--heading-color);
            margin: 0 0 30px 0;
        }

        .card {
            background-color: var(--card-background);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--card-shadow);
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .data-table th {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .data-table tr:hover {
            background-color: #f9f9f9;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 5px;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .time-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .time-row input[type="time"] {
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .time-row label {
            margin-right: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background-color: var(--success-color);
            color: #fff;
        }

        .message.error {
            background-color: var(--pending-color);
            color: #fff;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Function to convert 12-hour display time (e.g., 8:00 AM) to 24-hour format (e.g., 08:00:00)
        function convertTo24Hour(time12h) {
            // Handle cases where time might already be 24-hour (from input field) or empty
            if (time12h.includes(':') && !time12h.includes('M')) {
                return time12h + ':00';
            }

            const [time, modifier] = time12h.split(' ');
            if (!modifier) return time12h; // Guard for invalid format

            let [hours, minutes] = time.split(':');

            if (hours === '12') {
                hours = '00';
            }
            if (modifier === 'PM') {
                hours = parseInt(hours, 10) + 12;
            }
            return `${hours.toString().padStart(2, '0')}:${minutes.padStart(2, '0')}:00`;
        }

        function toggleDay(checkbox, dayNum) {
            var row = $(checkbox).closest('tr');
            var start_time_display = row.find('td:eq(0)').text().trim(); // e.g., 8:00 AM
            var end_time_display = row.find('td:eq(1)').text().trim(); // e.g., 10:00 AM
            var action = checkbox.checked ? 'add' : 'delete';

            var start_time_24 = convertTo24Hour(start_time_display);
            var end_time_24 = convertTo24Hour(end_time_display);

            $.post('update_day.php', {
                day_num: dayNum,
                // Sending 24-hour format time for consistency with database
                start_time: start_time_24,
                end_time: end_time_24,
                action: action
            }, function(resp) {
                if (resp.status === 'success') {
                    // Reload on success to update all time_id's if necessary
                    location.reload();
                } else {
                    alert(resp.message);
                    checkbox.checked = !checkbox.checked; // Revert checkbox state on error
                }
            }, 'json').fail(function() {
                alert("An unexpected error occurred during day update.");
                checkbox.checked = !checkbox.checked;
            });
        }


        function deleteTime(time_id) {
            if (confirm('Delete this time slot for all days?')) {
                // The PHP logic for delete will handle finding the correct time range 
                // using the time_id of *any* entry in that grouped row.
                $.post('', {
                    delete_time_id: 1,
                    time_id: time_id
                }, () => location.reload());
            }
        }
    </script>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-heart-pulse"></i> <span>DoctorPanel</span>
        </div>
        <nav class="sidebar-nav">
            <a href="doctor_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="add_speciality.php"><i class="fas fa-brain"></i> <span>Add Category & Speciality</span></a>
            <a href="add_day_time.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Add Day & Time</span></a>
            <a href="booking.php"><i class="fas fa-book-medical"></i> <span>Bookings</span></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Manage Availability</h1>
        </header>

        <div class="card">
            <?php if ($msg != ""): ?>
                <div class="message <?php echo $msg_type; ?>"><?php echo $msg; ?></div>
            <?php endif; ?>

            <?php if (!empty($time_slots)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <?php foreach ($days_map as $day): ?>
                                <th><?php echo $day; ?></th>
                            <?php endforeach; ?>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_slots as $slot): ?>
                            <tr>
                                <td><?php echo date("g:i A", strtotime($slot['start_time'])); ?></td>
                                <td><?php echo date("g:i A", strtotime($slot['end_time'])); ?></td>
                                <?php foreach ($days_map as $num => $day_name):
                                    $day_data = $slot['days'][$num] ?? null;
                                    $checked = $day_data ? 'checked' : '';
                                ?>
                                    <td>
                                        <input type="checkbox" onchange="toggleDay(this, <?php echo $num; ?>)" <?php echo $checked; ?>>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <button class="btn" onclick="deleteTime('<?php echo $slot['days'][array_key_first($slot['days'])]['time_id']; ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align:center; padding:20px;">No time slots added yet.</p>
            <?php endif; ?>

            <hr>
            <h3>Add New Time Slot</h3>
            <form method="POST">
                <div class="time-row">
                    <input type="time" name="start_time" required>
                    <input type="time" name="end_time" required>
                </div>
                <div class="time-row">
                    <?php foreach ($days_map as $num => $dname): ?>
                        <label><input type="checkbox" name="days[]" value="<?php echo $num; ?>"> <?php echo $dname; ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="add_time" class="btn">Save Time</button>
            </form>
        </div>
    </main>
</body>

</html>