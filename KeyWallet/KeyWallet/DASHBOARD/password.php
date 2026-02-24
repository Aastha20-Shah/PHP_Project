<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['editMode']);
unset($_SESSION['editData']);
include "config.php";
include 'lock_check.php';
requireLock();
include 'auth_check.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT name,password_manager_enabled FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$user_name = $user['name']; // <-- Add this line
$pm_enabled = $user && $user['password_manager_enabled'] ? true : false;

// AES-256 encryption/decryption
$secretKey = "my_super_secret_key_123"; // store securely

function encryptPassword($password, $key)
{
    $method = "AES-256-CBC";
    $iv = openssl_random_pseudo_bytes(16); // must be 16 bytes
    $encrypted = openssl_encrypt($password, $method, $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted); // prepend IV to ciphertext
}

function decryptPassword($data, $key)
{
    $data = base64_decode($data);
    if (strlen($data) < 16) {
        return $data;
    }

    $iv = substr($data, 0, 16); // first 16 bytes
    $ciphertext = substr($data, 16);
    return openssl_decrypt($ciphertext, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}

function safeDecrypt($data, $key)
{
    $decrypted = decryptPassword($data, $key);
    if ($decrypted === false || preg_match('/[^\x20-\x7E]/', $decrypted)) {
        // looks like plain text, return original
        return $data;
    }
    return $decrypted;
}

// --- Handle delete request ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT website FROM passwords WHERE id=$id AND user_id=$user_id");
    $row = $res->fetch_assoc();
    $website = $row['website'] ?? 'Unknown';

    $sql = ("DELETE FROM passwords WHERE id=$id AND user_id=$user_id");
    if ($conn->query($sql)) {
        logEvent($conn, $user_id, $user_name, "Delete password for $website", "success");
        echo "<script>alert('Password Deleted'); window.location.href='password.php';</script>";
    } else {
        logEvent($conn, $user_id, $user_name, "Failed to delete password for $website", "danger");
        echo "<script>alert('Error delete password!');</script>";
    }
    header("Location: password.php");
    exit;
}

// --- Handle add password ---
if (isset($_POST['save'])) {
    $website  = $conn->real_escape_string($_POST['website']);
    $url      = $conn->real_escape_string($_POST['url']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $tag      = $conn->real_escape_string($_POST['tag']);

    $encryptedPassword = $pm_enabled ? encryptPassword($password, $secretKey) : $password;

    $sql = ("INSERT INTO passwords (user_id, website,url,username,password,tag,updated) 
                  VALUES ($user_id,'$website','$url','$username','$encryptedPassword','$tag',NOW())");
    $_SESSION['saved'] = true;
    if ($conn->query($sql)) {
        logEvent($conn, $user_id, $user_name, "Added password for $website", "success");
        echo "<script>alert('Password saved!'); window.location.href='password.php';</script>";
    } else {
        logEvent($conn, $user_id, $user_name, "Failed to add password for $website", "danger");
        echo "<script>alert('Error saving password!');</script>";
    }
    $conn->close();
    header("Location: password.php");
    exit;
}
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM passwords WHERE id=$id AND user_id=$user_id");
    if ($row = $res->fetch_assoc()) {
        $_SESSION['editData'] = $row;
        $_SESSION['editMode'] = true;
        // header("Location: password.php");
        // exit;
    }
}

if (isset($_POST['update'])) {
    $id       = intval($_POST['id']);
    $website  = $conn->real_escape_string($_POST['website']);
    $url      = $conn->real_escape_string($_POST['url']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $tag      = $conn->real_escape_string($_POST['tag']);

    $update_pw = $pm_enabled ? encryptPassword($password, $secretKey) : $password;

    $sql = ("UPDATE passwords SET website='$website', url='$url', username='$username',
                  password='$update_pw', tag='$tag', updated=NOW()
                  WHERE id=$id AND user_id=$user_id");
    if ($conn->query($sql)) {
        logEvent($conn, $user_id, $user_name, "Upadate password for $website", "success");
        echo "<script>alert('Password Updated'); window.location.href='password.php';</script>";
    } else {
        logEvent($conn, $user_id, $user_name, "Failed to update password for $website", "danger");
        echo "<script>alert('Error update password!');</script>";
    }

    unset($_SESSION['editMode']);
    unset($_SESSION['editData']);
    header("Location: password.php");
    exit;
}

// --- Handle search & sort ---
$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sort   = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'website';
if (!in_array($sort, ['website', 'username', 'updated'])) $sort = 'website';

$sql = "SELECT * FROM passwords 
        WHERE user_id=$user_id
        AND (website LIKE '%$search%' 
             OR username LIKE '%$search%' 
             OR url LIKE '%$search%' 
             OR tag LIKE '%$search%')
        ORDER BY $sort ASC";
$result = $conn->query($sql);
$count = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Passwords - KeyVault</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --content-bg: #f8f9fc;
            --card-bg: #ffffff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --sidebar-accent: #5d78ff;
            --danger: #f87171;
            --transition: all 0.22s cubic-bezier(.175, .885, .32, 1.275);
            --glass: rgba(255, 255, 255, 0.02);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        body {
            display: block;
            min-height: 100vh;
            background: var(--content-bg);
            color: var(--text-dark);
        }

        .main {
            padding: 34px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .page-title {
            font-size: 30px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .muted {
            color: var(--text-light);
            font-size: 13px;
        }

        .search-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 14px;
            padding: 12px 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
            width: 420px;
            transition: var(--transition);
        }

        .search-box:focus-within {
            box-shadow: 0 8px 24px rgba(93, 120, 255, 0.12);
        }

        .search-box i {
            color: var(--text-light);
            margin-right: 12px;
            font-size: 16px;
        }

        .search-box input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 15px;
            width: 100%;
            color: var(--text-dark);
        }

        .btn {
            background: var(--sidebar-accent);
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 6px 14px rgba(93, 120, 255, 0.14);
            transition: var(--transition);
        }

        .btn.secondary {
            background: transparent;
            color: var(--text-dark);
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: none;
        }

        .btn:active {
            transform: translateY(1px);
        }

        .card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
        }

        .table-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        table.password-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        table th,
        table td {
            padding: 12px 14px;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        table th {
            text-transform: uppercase;
            font-size: 12px;
            color: var(--text-light);
            letter-spacing: 0.8px;
            font-weight: 600;
            background: linear-gradient(180deg, rgba(93, 120, 255, 0.03), transparent);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        tbody tr {
            transition: var(--transition);
            border-radius: 8px;
        }

        tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.03);
        }

        tbody tr td:first-child {
            font-weight: 700;
            color: var(--text-dark);
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 14px;
            /* bigger */
            font-weight: 350;
            /* bolder */
            color: black;
            /* white text */
            text-transform: capitalize;
            /* "Work" → "Work" (clean look) */
        }

        .pw {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", monospace;
            letter-spacing: 0.6px;
        }

        .action-icons i {
            margin-right: 12px;
            cursor: pointer;
            font-size: 15px;
            color: var(--text-light);
            transition: var(--transition);
        }

        .action-icons i:hover {
            color: var(--sidebar-accent);
            transform: scale(1.06);
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 900;
        }

        .modal {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            width: 520px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .modal h3 {
            margin-bottom: 10px;
            font-size: 18px;
            color: var(--text-dark);
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-size: 13px;
            color: var(--text-light);
        }

        input[type="text"],
        input[type="password"],
        input[type="url"] {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e6e9ef;
            outline: none;
            font-size: 14px;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            box-shadow: 0 6px 18px rgba(93, 120, 255, 0.08);
            border-color: rgba(93, 120, 255, 0.2);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 12px;
        }

        @media (max-width: 992px) {
            .main {
                padding: 20px;
                margin: 0 12px;
            }

            table.password-table {
                min-width: 600px;
            }
        }

        @media (max-width: 700px) {
            .modal {
                width: 92%;
            }

            table.password-table {
                min-width: auto;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

    <main class="main" role="main">
        <div class="header">
            <div>
                <h1 class="page-title">Passwords Overview</h1>
                <div class="muted" style="margin-top:6px;">Securely store and manage your passwords</div>
            </div>
            <form method="get" class="search-row">
                <div class="search-box" title="Search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search website, username or tag...">
                </div>
                <div class="table-actions">
                    <button type="button" class="btn" onclick="document.getElementById('addModal').style.display='flex'">+ Add Password</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-top">
                <div class="meta"><?= $count ?> items • Sorted by <strong><?= ucfirst($sort) ?></strong></div>
                <div class="table-actions">
                    <label class="muted nowrap">
                        Sort:
                        <select name="sort" onchange="this.form.submit()" form="sortForm">
                            <option value="website" <?= $sort == 'website' ? 'selected' : '' ?>>Website</option>
                            <option value="username" <?= $sort == 'username' ? 'selected' : '' ?>>Username</option>
                            <option value="updated" <?= $sort == 'updated' ? 'selected' : '' ?>>Last Updated</option>
                        </select>
                    </label>
                    <form method="get" id="sortForm" style="display:none">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>
            </div>

            <div style="overflow:auto;">
                <table class="password-table">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Tags</th>
                            <th>Last Updated</th>
                            <th style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($count == 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#888;padding:20px;">No passwords found</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php
                                $display_pw = safeDecrypt($row['password'], $secretKey);
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700"><?= htmlspecialchars($row['website']) ?></div>
                                        <div class="muted"><?= htmlspecialchars($row['url']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td>
                                        <?php if (isset($_GET['show']) && $_GET['show'] == $row['id']): ?>
                                            <?= htmlspecialchars($display_pw) ?>
                                        <?php else: ?>
                                            ••••••••
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="tag"><?= htmlspecialchars($row['tag']) ?></span>
                                    </td> <!-- Added -->
                                    <td><?= htmlspecialchars($row['updated']) ?></td>


                                    <td class="action-icons">
                                        <?php if (isset($_GET['show']) && $_GET['show'] == $row['id']): ?>
                                            <a href="password.php" title="Hide"><i class="fa-solid fa-lock-open"></i></a>
                                        <?php else: ?>
                                            <a href="password.php?show=<?= $row['id'] ?>" title="Show"><i class="fa-solid fa-lock"></i></a>
                                        <?php endif; ?>
                                        <a href="javascript:void(0);" class="copy-btn" data-password="<?= htmlspecialchars($display_pw) ?>" title="Copy">
                                            <i class="fa-solid fa-copy"></i>
                                        </a>

                                        <a href="password.php?edit=<?= $row['id'] ?>" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        <a href="password.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this password?')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>



            <!-- Add Modal -->
            <div class="modal-backdrop" id="addModal">
                <div class="modal">
                    <h3>Add Password</h3>
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Website</label>
                                <input type="text" name="website" required>
                            </div>
                            <div class="form-group">
                                <label>URL</label>
                                <input type="url" name="url">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" required>
                            </div>
                            <div class="form-group">
                                <label>Tag</label>
                                <input type="text" name="tag">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Password</label>
                                <input type="text" name="password" required>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                            <button type="submit" name="save" class="btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Edit Modal -->
            <div class="modal-backdrop" id="editModal" style="<?php if (isset($_SESSION['editMode']) && isset($_SESSION['editData'])) echo 'display:flex'; ?>">
                <div class="modal">
                    <h3>Edit Password</h3>
                    <form method="post">
                        <input type="hidden" name="id" value="<?= isset($_SESSION['editData']) ? $_SESSION['editData']['id'] : '' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Website</label>
                                <input type="text" name="website" value="<?= isset($_SESSION['editData']) ? htmlspecialchars($_SESSION['editData']['website']) : '' ?>" required>
                            </div>
                            <div class="form-group">
                                <label>URL</label>
                                <input type="url" name="url" value="<?= isset($_SESSION['editData']) ? htmlspecialchars($_SESSION['editData']['url']) : '' ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" value="<?= isset($_SESSION['editData']) ? htmlspecialchars($_SESSION['editData']['username']) : '' ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tag</label>
                                <input type="text" name="tag" value="<?= isset($_SESSION['editData']) ? htmlspecialchars($_SESSION['editData']['tag']) : '' ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Password</label>
                                <?php
                                $edit_pw = "";
                                if (isset($_SESSION['editData'])) {
                                    $edit_pw = safeDecrypt($_SESSION['editData']['password'], $secretKey);
                                }
                                ?>
                                <input type="text" name="password" value="<?= htmlspecialchars($edit_pw) ?>" required>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                            <button type="submit" name="update" class="btn">Update</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php if (isset($_SESSION['saved'])): ?>
                <script>
                    window.onload = function() {
                        // Hide modal if it was open
                        document.getElementById('addModal').style.display = 'none';
                        // Clear form fields
                        let form = document.querySelector('#addModal form');
                        if (form) form.reset();
                    };
                </script>
            <?php unset($_SESSION['saved']);
            endif; ?>
            <script>
                document.querySelectorAll('.copy-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const password = btn.getAttribute('data-password');
                        if (!password) return;

                        navigator.clipboard.writeText(password).then(() => {
                            alert('Password copied to clipboard!');
                        }).catch(err => {
                            console.error('Failed to copy: ', err);
                        });
                    });
                });
            </script>
        </div>
</body>

</html>