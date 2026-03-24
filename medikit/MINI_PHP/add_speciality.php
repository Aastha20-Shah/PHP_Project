<?php
session_start();
include("config.php"); // Database connection

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$msg = "";
$msg_type = ""; // To handle success or error messages

// AJAX HANDLER: Fetch specialities based on category
if (isset($_POST['action']) && $_POST['action'] == 'get_specialities') {
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        $category_id = intval($_POST['category_id']);
        $query = "SELECT id, doctor_speciality FROM speciality WHERE category_id = '$category_id' ORDER BY doctor_speciality";
        $result = mysqli_query($conn, $query);
        $options = '<option value="">-- Select Speciality --</option>';
        while ($row = mysqli_fetch_assoc($result)) {
            $options .= "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
        }
        echo $options;
        exit;
    } else {
        echo '<option value="">-- Select Category First --</option>';
        exit;
    }
}


// FORM HANDLER 1: Create a new speciality and assign it
if (isset($_POST['create_new_speciality'])) {
    $speciality_name = trim($_POST['doctor_speciality']);
    $category_id = intval($_POST['category_id']);

    if ($speciality_name != "" && $category_id > 0) {
        $sanitized_speciality = mysqli_real_escape_string($conn, $speciality_name);

        $check = mysqli_query($conn, "SELECT id FROM speciality WHERE doctor_speciality='$sanitized_speciality' AND category_id='$category_id'");

        $speciality_id = 0;

        if (mysqli_num_rows($check) > 0) {
            $spec = mysqli_fetch_assoc($check);
            $speciality_id = $spec['id'];
        } else {
            $insert_query = "INSERT INTO speciality (doctor_speciality, category_id) VALUES ('$sanitized_speciality', '$category_id')";
            if (mysqli_query($conn, $insert_query)) {
                $speciality_id = mysqli_insert_id($conn);
            } else {
                $msg = "Database error: Could not create the new speciality.";
                $msg_type = "error";
            }
        }

        if ($speciality_id > 0) {
            $check_doc_spec = mysqli_query($conn, "SELECT * FROM doctor_speciality WHERE doctor_id='$doctor_id' AND speciality_id='$speciality_id'");
            if (mysqli_num_rows($check_doc_spec) == 0) {
                mysqli_query($conn, "INSERT INTO doctor_speciality (doctor_id, speciality_id) VALUES ('$doctor_id', '$speciality_id')");
                // Update users table also
                mysqli_query($conn, "UPDATE users SET speciality_id='$speciality_id', category_id='$category_id' WHERE id='$doctor_id'");
                $msg = "New speciality created and assigned successfully!";
                $msg_type = "success";
            } else {
                $msg = "This speciality already exists and is assigned to you.";
                $msg_type = "error";
            }
        }
    } else {
        $msg = "Speciality name and category are required.";
        $msg_type = "error";
    }
}

// FORM HANDLER 2: Assign an existing speciality
if (isset($_POST['assign_speciality'])) {
    $category_id = intval($_POST['category_id_for_assign']);
    $speciality_id = intval($_POST['speciality_id']);

    if ($category_id > 0 && $speciality_id > 0) {
        $check_doc_spec = mysqli_query($conn, "SELECT * FROM doctor_speciality WHERE doctor_id='$doctor_id' AND speciality_id='$speciality_id'");
        if (mysqli_num_rows($check_doc_spec) == 0) {
            mysqli_query($conn, "INSERT INTO doctor_speciality (doctor_id, speciality_id) VALUES ('$doctor_id', '$speciality_id')");
        }
        // Always update users table with the latest selection
        mysqli_query($conn, "UPDATE users SET category_id='$category_id' WHERE id='$doctor_id'");
        $msg = "Speciality assigned to your profile successfully!";
        $msg_type = "success";
    } else {
        $msg = "Please select a category and a speciality.";
        $msg_type = "error";
    }
}


// Fetch all categories for the dropdowns
$categories_result = mysqli_query($conn, "SELECT * FROM category ORDER BY category_name ASC");

// Fetch the specialities linked to the current doctor for the table
$doctor_specialities_result = mysqli_query($conn, "SELECT s.doctor_speciality 
    FROM doctor_speciality ds 
    JOIN speciality s ON ds.speciality_id = s.id 
    WHERE ds.doctor_id = '$doctor_id' 
    ORDER BY s.doctor_speciality ASC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Speciality - Doctor Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        }

        /* Base Styles */
        body {
            font-family: 'Nunito Sans', sans-serif;
            margin: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
        }

        /* Sidebar Navigation */
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

        /* Main Content */
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

        /* Layout Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 25px;
            align-items: flex-start;
        }

        .card {
            background-color: var(--card-background);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .card-header {
            font-size: 20px;
            font-weight: 700;
            color: var(--heading-color);
            margin-top: 0;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .btn {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            color: #fff;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-submit {
            background-color: var(--primary-color);
        }

        .btn-submit:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        /* Message Styling */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Table Styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .data-table th {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
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
            <a href="add_speciality.php" class="active"><i class="fas fa-brain"></i> <span>Add Category & Speciality</span></a>
            <a href="add_day_time.php"><i class="fas fa-calendar-alt"></i> <span>Add Day & Time</span></a>
            <a href="booking.php"><i class="fas fa-book-medical"></i> <span>Bookings</span></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Manage Your Specialities</h1>
        </header>

        <?php if ($msg != ""): ?>
            <div class="message <?php echo $msg_type; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Left Column: Forms -->
            <div class="forms-column">
                <!-- Card 1: Assign Existing Speciality -->
                <div class="card">
                    <h2 class="card-header"><i class="fas fa-link"></i> Assign Existing Speciality</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="assign_category_id">1. Select Category</label>
                            <select id="assign_category_id" name="category_id_for_assign" class="form-control" required>
                                <option value="">-- Choose a Category --</option>
                                <?php mysqli_data_seek($categories_result, 0); // Reset pointer
                                while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assign_speciality_id">2. Select Speciality</label>
                            <select id="assign_speciality_id" name="speciality_id" class="form-control" required>
                                <option value="">-- Select Category First --</option>
                            </select>
                        </div>
                        <button type="submit" name="assign_speciality" class="btn btn-submit">Assign to Profile</button>
                    </form>
                </div>

                <!-- Card 2: Create New Speciality -->
                <div class="card" style="margin-top: 25px;">
                    <h2 class="card-header"><i class="fas fa-plus-circle"></i> Create New Speciality</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="create_category_id">1. Select Category</label>
                            <select id="create_category_id" name="category_id" class="form-control" required>
                                <option value="">-- Choose a Category --</option>
                                <?php mysqli_data_seek($categories_result, 0); // Reset pointer
                                while ($cat = mysqli_fetch_assoc($categories_result)): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="doctor_speciality">2. New Speciality Name</label>
                            <input type="text" id="doctor_speciality" name="doctor_speciality" class="form-control" placeholder="e.g., Cardiac Surgery" required>
                        </div>
                        <button type="submit" name="create_new_speciality" class="btn btn-submit">Create & Assign</button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Your Current Specialities -->
            <div class="card">
                <h2 class="card-header"><i class="fas fa-user-md"></i> Your Current Specialities</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Speciality Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($doctor_specialities_result) > 0): ?>
                            <?php while ($spec = mysqli_fetch_assoc($doctor_specialities_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($spec['doctor_speciality']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td style="text-align: center;">You have not added any specialities yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#assign_category_id').change(function() {
                var categoryId = $(this).val();
                if (categoryId) {
                    $.ajax({
                        type: 'POST',
                        url: '', // Post to the same file
                        data: {
                            action: 'get_specialities',
                            category_id: categoryId
                        },
                        success: function(response) {
                            $('#assign_speciality_id').html(response);
                        }
                    });
                } else {
                    $('#assign_speciality_id').html('<option value="">-- Select Category First --</option>');
                }
            });
        });
    </script>
</body>

</html>