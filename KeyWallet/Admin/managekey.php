<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: index.php");
    exit;
}

// Fetch all users for dropdown
$users_result = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Keys - Admin Panel</title>
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
                <a href="manageKey.php"><i class="fas fa-key"></i> Manage Keys</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto col-lg-10 content">
                <h2>Manage Keys</h2>
                <p class="text-muted">Select a user to view their data.</p>

                <!-- User Selection -->
                <div class="card p-3 mb-4">
                    <h5><i class="fas fa-user"></i> Select User</h5>
                    <select id="selectUser" class="form-select mt-2">
                        <option value="">-- Select User --</option>
                        <?php while ($user = mysqli_fetch_assoc($users_result)) : ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Tabs -->
                <div id="userDataCard" class="card p-3" style="display:none;">
                    <ul class="nav nav-tabs" id="dataTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="passwords-tab" data-bs-toggle="tab" data-bs-target="#passwords" type="button" role="tab">Passwords</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">Documents</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">Payment Cards</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" type="button" role="tab">Media</button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3">
                        <!-- Passwords Tab -->
                        <div class="tab-pane fade show active" id="passwords" role="tabpanel">
                            <table class="table table-striped align-middle" id="passwordsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Website</th>
                                        <th>URL</th>
                                        <th>Username</th>
                                        <th>Password</th>
                                        <th>Tag</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <table class="table table-striped align-middle" id="documentsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Tags</th>
                                        <th>Notes</th>
                                        <th>File Path</th>
                                        <th>Created At</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Payment Cards Tab -->
                        <div class="tab-pane fade" id="payment" role="tabpanel">
                            <table class="table table-striped align-middle" id="paymentTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Holder</th>
                                        <th>Brand</th>
                                        <th>Card Number</th>
                                        <th>Expiry</th>
                                        <th>Note</th>
                                        <th>Updated At</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Media Cards Tab -->
                        <div class="tab-pane fade" id="media" role="tabpanel">
                            <table class="table table-striped align-middle" id="mediaTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Type</th>
                                        <th>Tags</th>
                                        <th>Uploaded At</th>
                                        <th>File Path</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectUser').addEventListener('change', function() {
            const userId = this.value;
            const card = document.getElementById('userDataCard');
            const passwordsTbody = document.querySelector('#passwordsTable tbody');
            const documentsTbody = document.querySelector('#documentsTable tbody');
            const paymentTbody = document.querySelector('#paymentTable tbody');
            const mediaTbody = document.querySelector('#mediaTable tbody');

            // Hide card and clear previous data
            card.style.display = 'none';
            passwordsTbody.innerHTML = '';
            documentsTbody.innerHTML = '';
            paymentTbody.innerHTML = '';
            mediaTbody.innerHTML = '';

            if (!userId) return;

            const fetchUrl = `/KeyWallet/Admin/fetch_user_keys.php?user_id=${userId}`;

            fetch(fetchUrl)
                .then(response => {
                    if (!response.ok) throw new Error(`Network error: ${response.status} - ${response.statusText}`);
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        alert('Could not fetch data: ' + data.error);
                        return;
                    }

                    // Passwords
                    if (data.passwords && data.passwords.length > 0) {
                        data.passwords.forEach(item => {
                            passwordsTbody.innerHTML += `<tr>
                        <td>${item.id}</td>
                        <td>${item.website || ''}</td>
                        <td>${item.url || ''}</td>
                        <td>${item.username || ''}</td>
                        <td>${item.password || ''}</td>
                        <td>${item.tag || ''}</td>
                        <td>${item.updated || ''}</td>
                    </tr>`;
                        });
                    } else {
                        passwordsTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No passwords found.</td></tr>`;
                    }

                    // Documents
                    if (data.documents && data.documents.length > 0) {
                        data.documents.forEach(item => {
                            documentsTbody.innerHTML += `<tr>
                        <td>${item.id}</td>
                        <td>${item.name || ''}</td>
                        <td>${item.size || ''}</td>
                        <td>${item.tags || ''}</td>
                        <td>${item.notes || ''}</td>
                        <td>${item.file_path || ''}</td>
                        <td>${item.created_at || ''}</td>
                    </tr>`;
                        });
                    } else {
                        documentsTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No documents found.</td></tr>`;
                    }

                    // Payment Cards
                    if (data.paymentcards && data.paymentcards.length > 0) {
                        data.paymentcards.forEach(item => {
                            paymentTbody.innerHTML += `<tr>
                        <td>${item.id}</td>
                        <td>${item.holder || ''}</td>
                        <td>${item.brand || ''}</td>
                        <td>${item.card_number || ''}</td>
                        <td>${item.expiry || ''}</td>
                        <td>${item.note || ''}</td>
                        <td>${item.updated_at || ''}</td>
                    </tr>`;
                        });
                    } else {
                        paymentTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No payment cards found.</td></tr>`;
                    }

                    // Media
                    // Media
                    if (data.media && data.media.length > 0) {
                        data.media.forEach(item => {
                            mediaTbody.innerHTML += `<tr>
            <td>${item.id}</td>
            <td>${item.name || ''}</td>
            <td>${item.size || ''}</td>
            <td>${item.type || ''}</td>
            <td>${item.tags || ''}</td>
            <td>${item.uploaded_at || ''}</td>
            <td>${item.file_path || ''}</td>
        </tr>`;
                        });
                    } else {
                        mediaTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No media found.</td></tr>`;
                    }

                    // Show card
                    card.style.display = 'block';
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('A critical error occurred. Check console for details.');
                });
        });
    </script>
</body>

</html>