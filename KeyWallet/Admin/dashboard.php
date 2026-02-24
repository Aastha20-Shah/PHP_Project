<?php
session_start();
require_once 'config.php';

// --- Admin check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// --- Fetch all users for the sidebar list ---
$users_result = mysqli_query($conn, "SELECT id, name, email FROM users ORDER BY name ASC");

// --- Get selected user ID from the URL ---
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$selected_user = null;

// --- Initialize variables to avoid errors if no user is selected ---
$total_keys = $total_docs = $total_cards = 0;
$reused_passwords = $old_passwords = $weak_passwords = 0;
$passwords_result = $payment_cards_result = $documents_result = null;

// --- Fetch all data if a user has been selected ---
if ($selected_user_id > 0) {
    $user_check_query = "SELECT * FROM users WHERE id = $selected_user_id";
    $user_check_result = mysqli_query($conn, $user_check_query);

    if (mysqli_num_rows($user_check_result) > 0) {
        $selected_user = mysqli_fetch_assoc($user_check_result);

        // --- Fetch summary counts ---
        $total_keys   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM passwords WHERE user_id=$selected_user_id"))['total'] ?? 0;
        $total_docs   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM documents WHERE user_id=$selected_user_id"))['total'] ?? 0;
        $total_cards  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM payment_cards WHERE user_id=$selected_user_id"))['total'] ?? 0;

        // --- Password health checks ---
        $reused_passwords = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM passwords WHERE user_id=$selected_user_id AND reused=1"))['total'] ?? 0;
        $old_passwords    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM passwords WHERE user_id=$selected_user_id AND DATEDIFF(NOW(), updated) > 365"))['total'] ?? 0;
        $weak_passwords   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM passwords WHERE user_id=$selected_user_id AND strength='weak'"))['total'] ?? 0;

        // --- Fetch detailed lists for tables ---
        $passwords_result = mysqli_query($conn, "SELECT * FROM passwords WHERE user_id=$selected_user_id ORDER BY updated DESC");
        $payment_cards_result = mysqli_query($conn, "SELECT * FROM payment_cards WHERE user_id=$selected_user_id ORDER BY updated DESC");
        $documents_result = mysqli_query($conn, "SELECT * FROM documents WHERE user_id=$selected_user_id ORDER BY updated DESC");
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Key Vault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }

        .sidebar {
            height: 100vh;
            background: #212529;
            color: #fff;
            padding: 1rem;
            overflow-y: auto;
        }

        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
            display: block;
            padding: 0.5rem 1rem;
            border-radius: 5px;
        }

        .sidebar a:hover,
        .sidebar .active-link {
            background: #343a40;
            color: #fff;
        }

        .sidebar .user-list a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .active-user {
            background-color: #0d6efd !important;
            color: white !important;
        }

        .active-user a {
            color: white !important;
            font-weight: bold;
        }

        .content {
            padding: 2rem;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .summary-card {
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
            color: #fff;
        }

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">

            <nav class="col-md-3 col-lg-2 bg-dark sidebar">
                <h4 class="text-white mb-3">🔑 Key Vault Admin</h4>
                <p class="text-white-50 small">Logged in as: <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
                <hr class="text-secondary">

                <a href="dashboard.php" class="active-link"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
                <a href="managekey.php"><i class="fas fa-users fa-fw me-2"></i>Manage Users</a>

                <h5 class="text-white mt-4">Users</h5>
                <div class="user-list list-group list-group-flush">
                    <?php mysqli_data_seek($users_result, 0); // Reset pointer for second loop 
                    ?>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                        <a href="?user_id=<?= $user['id'] ?>" class="list-group-item list-group-item-action bg-dark text-white p-2 <?= ($selected_user_id == $user['id']) ? 'active-user' : '' ?>">
                            <?= htmlspecialchars($user['name']) ?>
                            <br><small class="text-white-50"><?= htmlspecialchars($user['email']) ?></small>
                        </a>
                    <?php endwhile; ?>
                </div>
                <a href="logout.php" class="btn btn-danger w-100 mt-auto"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 content">
                <?php if ($selected_user): ?>
                    <h2>Dashboard for: <?= htmlspecialchars($selected_user['name']) ?></h2>
                    <p class="text-muted"><?= htmlspecialchars($selected_user['email']) ?></p>

                    <div class="row">
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-primary">Keys<br><strong><?= $total_keys ?></strong></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-success">Documents<br><strong><?= $total_docs ?></strong></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-warning text-dark">Cards<br><strong><?= $total_cards ?></strong></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-danger">Reused<br><strong><?= $reused_passwords ?></strong></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-info">Old<br><strong><?= $old_passwords ?></strong></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="summary-card bg-secondary">Weak<br><strong><?= $weak_passwords ?></strong></div>
                        </div>
                    </div>

                    <div class="card">
                        <h5 class="card-header"><i class="fas fa-key me-2"></i>Stored Keys / Passwords</h5>
                        <div class="table-responsive p-3">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Website</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Tag</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($passwords_result && mysqli_num_rows($passwords_result) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($passwords_result)): ?>
                                            <tr>
                                                <td>#KV<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= htmlspecialchars($row['website']) ?></td>
                                                <td><?= htmlspecialchars($row['username']) ?></td>
                                                <td><span class="font-monospace">••••••••</span></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['tag']) ?></span></td>
                                                <td><?= date('Y-m-d', strtotime($row['updated'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No passwords found for this user.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="text-center mt-5">
                        <i class="fas fa-user-circle fa-4x text-muted"></i>
                        <h3 class="mt-3">Welcome to the Admin Dashboard</h3>
                        <p class="text-muted">Please select a user from the sidebar to view their vault details.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php mysqli_close($conn); ?>