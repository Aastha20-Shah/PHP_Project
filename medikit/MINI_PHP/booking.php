<?php
session_start();
include("config.php"); // Database connection

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Handle Accept, Visited, or Reject action
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $action = $_GET['action'];

    // All status changes must set patient_notified = 0 (unread)
    if ($action === 'accept') {
        $update_query = "UPDATE visit_booking SET status = 'accepted', patient_notified = 0 WHERE id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $booking_id, $doctor_id);
        $stmt->execute();
    } elseif ($action === 'visited') {
        // Mark as visited, set is_visited = 1, and set patient_notified = 0
        $update_query = "UPDATE visit_booking SET status = 'visited', is_visited = 1, patient_notified = 0 WHERE id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $booking_id, $doctor_id);
        $stmt->execute();
    } elseif ($action === 'reject') {
        $update_query = "UPDATE visit_booking SET status = 'rejected', patient_notified = 0 WHERE id = ? AND doctor_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $booking_id, $doctor_id);
        $stmt->execute();
    }

    header("Location: booking.php");
    exit;
}

// Fetch bookings for this doctor
$booking_query = "
SELECT vb.id as booking_id, 
       p.firstname as patient_firstname, p.lastname as patient_lastname, p.phone_number, p.gender, p.address,
       s.doctor_speciality, vb.appointment_date, dat.start_time, dat.end_time, dad.day, vb.Note, vb.is_visited, vb.status
FROM visit_booking vb
LEFT JOIN patient p ON vb.patient_id = p.id
LEFT JOIN speciality s ON vb.speciality_id = s.id
LEFT JOIN doctor_available_time dat ON vb.time_id = dat.id
LEFT JOIN doctor_available_day dad ON dat.day_id = dad.id
WHERE vb.doctor_id = $doctor_id
ORDER BY dad.day, dat.start_time
";
$bookings_result = mysqli_query($conn, $booking_query);

$days_map = ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Doctor Panel</title>
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
            min-width: 800px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
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

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 12px;
            color: #fff;
            text-align: center;
        }

        .status-visited {
            background-color: var(--success-color);
        }

        .status-pending {
            background-color: var(--pending-color);
        }

        .btn-accept,
        .btn-visited,
        .btn-reject {
            display: inline-block;
            padding: 6px 10px;
            margin-left: 5px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            color: #fff;
            font-weight: 700;
        }

        .btn-accept {
            background-color: #27ae60;
        }

        .btn-accept:hover {
            background-color: #219150;
        }

        .btn-visited {
            background-color: #8e44ad;
        }

        .btn-visited:hover {
            background-color: #732d91;
        }

        .btn-reject {
            background-color: #e74c3c;
        }

        .btn-reject:hover {
            background-color: #c0392b;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-heart-pulse"></i> <span>DoctorPanel</span>
        </div>
        <nav class="sidebar-nav">
            <a href="doctor_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="add_speciality.php"><i class="fas fa-brain"></i> <span>Add Category & Speciality</span></a>
            <a href="add_day_time.php"><i class="fas fa-calendar-alt"></i> <span>Add Day & Time</span></a>
            <a href="booking.php" class="active"><i class="fas fa-book-medical"></i> <span>Bookings</span></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>My Patient Bookings</h1>
        </header>

        <div class="card">
            <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Phone</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Speciality</th>
                            <th>Note</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = mysqli_fetch_assoc($bookings_result)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['patient_firstname'] . ' ' . $booking['patient_lastname']); ?></td>
                                <td><?php echo htmlspecialchars($booking['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars($booking['appointment_date']); ?></td>
                                <td><?php echo htmlspecialchars($days_map[$booking['day']]); ?></td>
                                <td><?php echo date("g:i A", strtotime($booking['start_time'])) . " - " . date("g:i A", strtotime($booking['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($booking['doctor_speciality']); ?></td>
                                <td><?php echo htmlspecialchars($booking['Note']); ?></td>
                                <td>
                                    <?php
                                    if ($booking['status'] == 'pending' || $booking['status'] == '' || $booking['status'] == null) {
                                        echo '<span class="status-badge status-pending">Pending</span> ';
                                        echo '<a href="?action=accept&booking_id=' . $booking['booking_id'] . '" class="btn-accept">Accept</a>';
                                        echo '<a href="?action=reject&booking_id=' . $booking['booking_id'] . '" class="btn-reject" onclick="return confirm(\'Are you sure you want to reject this booking?\')">Reject</a>';
                                    } elseif ($booking['status'] == 'accepted') {
                                        echo '<span class="status-badge" style="background:#2980b9;">Accepted</span> ';
                                        echo '<a href="?action=visited&booking_id=' . $booking['booking_id'] . '" class="btn-visited">Mark Visited</a>';
                                    } elseif ($booking['status'] == 'visited') {
                                        echo '<span class="status-badge status-visited">Visited</span>';
                                    } elseif ($booking['status'] == 'rejected') {
                                        echo '<span class="status-badge" style="background:#e74c3c;">Rejected</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 20px;">No bookings found.</p>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>