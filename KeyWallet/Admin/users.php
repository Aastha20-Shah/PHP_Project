<?php
session_start();
require_once 'config.php';

// Prevent browser caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if admin is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $id = intval($_POST['id']);
    $delete = mysqli_query($conn, "DELETE FROM users WHERE id=$id");
    if ($delete) {
        echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed.']);
    }
    exit;
}
// Fetch all users
$users_result = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            height: 100vh;
            background: #212529;
            color: #fff;
            padding-top: 1rem;
        }

        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
            display: block;
            padding: 0.75rem 1rem;
            border-radius: 5px;
        }

        .sidebar a:hover {
            background: #343a40;
            color: #fff;
        }

        .content {
            padding: 2rem;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0px 2px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar">
                <h4 class="text-white ps-3 mb-4">🔑 Key Vault</h4>
                <a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="managekey.php"><i class="fas fa-key"></i> Manage Keys</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto col-lg-10 content">
                <h2>Manage Users</h2>
                <p class="text-muted">View, add, and manage users of the Key Vault system.</p>

                <!-- Users Table -->
                <div class="card p-3">
                    <h5><i class="fas fa-users"></i> All Users</h5>
                    <table class="table table-striped align-middle mt-2" id="allUsersTable">
                        <thead class="table-dark">
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                <tr data-id="<?php echo $user['id']; ?>">
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger deleteBtn"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete user
        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this user?')) return;
                const tr = this.closest('tr');
                const userId = tr.dataset.id;

                fetch('users.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            action: 'delete_user',
                            id: userId
                        })
                    }).then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) location.reload();
                    });
            });
        });
    </script>

</body>

</html>

<?php
mysqli_close($conn);
?>