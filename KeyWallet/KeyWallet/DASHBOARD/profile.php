<?php
include 'config.php';
include 'auth_check.php'; // This should start the session and define $user_id

// --- START: PROFILE UPDATE LOGIC ---
// This part handles the form submission when a user saves changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = $_POST['name'] ?? '';
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Create a response array to send back as JSON
    $response = [];

    if ($password && $password !== $confirm) {
        $response = ['status' => 'error', 'message' => 'Passwords do not match'];
    } else {
        // Handle avatar upload
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            // Create uploads directory if it doesn't exist
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $avatarPath = 'uploads/' . uniqid() . '.' . $ext;
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarPath)) {
                $avatarPath = null; // Failed to move file
            }
        }

        // Build the SQL query dynamically
        $params = [];
        $sql_parts = [];
        $types = '';

        if (!empty($name)) {
            $sql_parts[] = "name=?";
            $params[] = $name;
            $types .= 's';
        }
        if (!empty($email)) {
            $sql_parts[] = "email=?";
            $params[] = $email;
            $types .= 's';
        }
        if (!empty($password)) {
            $sql_parts[] = "password=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= 's';
        }
        if ($avatarPath) {
            $sql_parts[] = "avatar=?";
            $params[] = $avatarPath;
            $types .= 's';
        }

        if (!empty($sql_parts)) {
            $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id=?";
            $params[] = $user_id;
            $types .= 'i';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Profile updated successfully!'];
            } else {
                $response = ['status' => 'error', 'message' => 'Database error: ' . $stmt->error];
            }
            $stmt->close();
        } else {
            $response = ['status' => 'info', 'message' => 'No changes were submitted.'];
        }
    }
    // Stop script execution and send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// --- END: PROFILE UPDATE LOGIC ---


// --- START: FETCH USER DATA FOR DISPLAY ---
// Fetch current user data to display on the page
$stmt = $conn->prepare("SELECT name, email, avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Function to get initials from a name (e.g., "John Doe" -> "JD")
function getInitials($name)
{
    $initials = '';
    $words = explode(' ', $name);
    if (!empty($words[0])) {
        $initials .= strtoupper(substr($words[0], 0, 1));
    }
    if (count($words) > 1) {
        $initials .= strtoupper(substr(end($words), 0, 1));
    } elseif (strlen($name) > 1) {
        // Fallback for single-word names
        $initials .= strtoupper(substr($name, 1, 1));
    }
    return $initials;
}

$initials = getInitials($user['name'] ?? 'User');
// --- END: FETCH USER DATA FOR DISPLAY ---
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | KeyWallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS goes here. No changes needed. */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid #1e293b;
        }

        .logo {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: 700;
            color: white;
        }

        .logo i {
            margin-right: 10px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            border: none;
        }

        .btn-primary {
            background: #2f4cff;
            color: white;
        }

        .btn-primary:hover {
            background: #1a2fb8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(47, 76, 255, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #2f4cff;
            color: white;
        }

        .btn-outline:hover {
            background: #2f4cff;
            color: white;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        .profile-sidebar {
            background: #1e293b;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            height: fit-content;
        }

        .user-info {
            text-align: center;
            margin-bottom: 25px;
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            position: relative;
            border: 4px solid #2f4cff;
            overflow: hidden;
            background: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2f4cff;
            font-size: 48px;
            font-weight: bold;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #2f4cff;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.3s;
            opacity: 0;
        }

        .avatar:hover .avatar-edit {
            opacity: 1;
        }

        .user-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
            color: #f8fafc;
        }

        .user-info p {
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .user-stats {
            display: flex;
            justify-content: space-around;
            margin: 25px 0;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .stat-label {
            font-size: 14px;
            color: #94a3b8;
        }

        .profile-menu {
            list-style: none;
            margin-top: 20px;
        }

        .profile-menu li {
            margin-bottom: 8px;
        }

        .profile-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .profile-menu a:hover,
        .profile-menu a.active {
            background: #334155;
            color: #2f4cff;
        }

        .profile-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .profile-main {
            background: #1e293b;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        }

        .section-title {
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #334155;
            display: flex;
            align-items: center;
            color: #f8fafc;
        }

        .section-title i {
            margin-right: 12px;
            color: #2f4cff;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #e2e8f0;
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 1px solid #475569;
            border-radius: 8px;
            background: #0f172a;
            color: #f8fafc;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #2f4cff;
            box-shadow: 0 0 0 3px rgba(47, 76, 255, 0.2);
        }

        .form-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        footer {
            text-align: center;
            padding: 30px 0;
            margin-top: 50px;
            color: #94a3b8;
            font-size: 14px;
            border-top: 1px solid #1e293b;
        }

        /* For Status Messages */
        #formStatus {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            display: none;
        }

        #formStatus.success {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4ade80;
        }

        #formStatus.error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f87171;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="logo"><i class="fas fa-key"></i> KeyWallet</div>
            <div class="user-actions">
                <a href="sidebar.html" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="logout.php" class="btn btn-outline" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>

        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="user-info">
                    <div class="avatar" id="avatarPreview">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="User Avatar">
                        <?php else: ?>
                            <?php echo $initials; ?>
                        <?php endif; ?>
                        <div class="avatar-edit" title="Change Avatar" onclick="document.getElementById('avatarInput').click();">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>

                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <div class="user-stats">
                    <div class="stat">
                        <div class="stat-value">247</div>
                        <div class="stat-label">Passwords</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">12</div>
                        <div class="stat-label">Devices</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">98%</div>
                        <div class="stat-label">Strength</div>
                    </div>
                </div>

                <ul class="profile-menu">
                    <li><a href="#" class="active">
                            <font color="White"><i class="fas fa-user-circle"></i> Profile Information
                            </font>
                        </a></li>
                </ul>
            </div>

            <div class="profile-main">
                <h2 class="section-title"><i class="fas fa-user-edit"></i> Edit Profile Information</h2>

                <form id="profileForm" method="post" enctype="multipart/form-data">
                    <div class="form-grid">
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;">

                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="window.location.reload();">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <div id="formStatus"></div>
                </form>
            </div>
        </div>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> KeyWallet. All rights reserved.</p>
        </footer>
    </div>

    <script>
        // Avatar preview handler
        document.getElementById('avatarInput').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarPreview = document.getElementById('avatarPreview');
                    // Clear previous content (initials or old image)
                    avatarPreview.innerHTML = `<img src="${e.target.result}" alt="New Avatar Preview">
                                              <div class="avatar-edit" title="Change Avatar" onclick="document.getElementById('avatarInput').click();">
                                                  <i class="fas fa-camera"></i>
                                              </div>`;
                };
                reader.readAsDataURL(file);
            }
        });

        // AJAX Form Submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const statusDiv = document.getElementById('formStatus');
            const submitButton = this.querySelector('button[type="submit"]');

            statusDiv.style.display = 'none';
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    statusDiv.textContent = data.message;
                    statusDiv.className = data.status; // 'success' or 'error'
                    statusDiv.style.display = 'block';

                    if (data.status === 'success') {
                        // Refresh the page after 2 seconds to show all changes
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    statusDiv.textContent = 'An unexpected error occurred. Please try again.';
                    statusDiv.className = 'error';
                    statusDiv.style.display = 'block';
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Save Changes';
                });
        });
    </script>
</body>

</html>