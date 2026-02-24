<?php
session_start();
include "config.php";
include 'lock_check.php';
requireLock();
include 'auth_check.php';

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];

// --- Handle Add Document ---
if (isset($_POST['save'])) {
    $tags  = $conn->real_escape_string($_POST['tags']);
    $notes = $conn->real_escape_string($_POST['notes']);

    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $fileName   = basename($_FILES['document']['name']);
        $fileTmp    = $_FILES['document']['tmp_name'];
        $fileSize   = $_FILES['document']['size'];
        $fileTarget = "uploads/" . time() . "_" . $fileName;

        // Ensure uploads folder exists
        if (!is_dir("uploads")) {
            mkdir("uploads", 0777, true);
        }

        if (move_uploaded_file($fileTmp, $fileTarget)) {
            $sizeKB = round($fileSize / 1024) . " KB";
            $sql = "INSERT INTO documents (user_id, name, size, tags, notes, file_path)
                    VALUES ('$user_id', '$fileName', '$sizeKB', '$tags', '$notes', '$fileTarget')";
            $conn->query($sql);
            logEvent($conn, $user_id, $_SESSION['user_name'], "File Upload: $fileName", "success");
            header("Location: documents.php");
        }
    }
}

// --- Handle Update Document ---
if (isset($_POST['update'])) {
    $id    = intval($_POST['id']);
    $name  = $conn->real_escape_string($_POST['name']);
    $tags  = $conn->real_escape_string($_POST['tags']);
    $notes = $conn->real_escape_string($_POST['notes']);

    $sql = "UPDATE documents SET name='$name', tags='$tags', notes='$notes' WHERE id='$id' AND user_id='$user_id'";
    $conn->query($sql);
    $res = $conn->query("SELECT name FROM documents WHERE id=$id AND user_id=$user_id");
    $row = $res->fetch_assoc();
    $fileName = $row['name'] ?? 'Unknown';
    logEvent($conn, $user_id, $_SESSION['user_name'], "File Updated: $fileName", "success");
    header("Location: documents.php");
}
// --- Handle Edit Request ---
$editDoc = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM documents WHERE id='$editId' AND user_id='$user_id'");
    if ($res->num_rows > 0) {
        $editDoc = $res->fetch_assoc();
    }
}

// --- Handle Delete Document ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT file_path FROM documents WHERE id='$id' AND user_id='$user_id'");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (file_exists($row['file_path'])) {
            unlink($row['file_path']); // delete file
        }
        $res = $conn->query("SELECT name FROM documents WHERE id=$id AND user_id=$user_id");
        $row = $res->fetch_assoc();
        $fileName = $row['name'] ?? 'Unknown';
    }
    $conn->query("DELETE FROM documents WHERE id='$id' AND user_id='$user_id'");
    logEvent($conn, $user_id, $_SESSION['user_name'], "File Deleted: $fileName", "success");
}
/* --------------------------
   SEARCH & SORT HANDLING
---------------------------*/
$where = "WHERE user_id='$user_id'";
$orderBy = "ORDER BY id DESC";

// Search
if (!empty($_GET['q'])) {
    $q = $conn->real_escape_string($_GET['q']);
    $where .= " AND (name LIKE '%$q%' OR tags LIKE '%$q%' OR notes LIKE '%$q%')";
}

// Sort
if (!empty($_GET['sort'])) {
    $sort = $_GET['sort'];
    if ($sort == "Name") {
        $orderBy = "ORDER BY name ASC";
    } elseif ($sort == "Date") {
        $orderBy = "ORDER BY created_at DESC";
    } elseif ($sort == "Size") {
        $orderBy = "ORDER BY size+0 DESC";
    }
}
// --- Fetch Documents ---
$docs = $conn->query("SELECT * FROM documents $where $orderBy");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Documents - KeyVault</title>
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

        table.doc-table {
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
            gap: 6px;
            align-items: center;
            padding: 4px 10px;
            font-size: 15px;
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

        .action-icons .delete:hover {
            color: var(--danger);
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
        input[type="file"],
        textarea {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e6e9ef;
            outline: none;
            font-size: 14px;
        }

        input:focus,
        textarea:focus {
            box-shadow: 0 6px 18px rgba(93, 120, 255, 0.08);
            border-color: rgba(93, 120, 255, 0.2);
        }

        textarea {
            resize: vertical;
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

            table.doc-table {
                min-width: 600px;
            }
        }

        @media (max-width: 700px) {
            .modal {
                width: 92%;
            }

            table.doc-table {
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
                <h1 class="page-title">Documents Overview</h1>
                <div class="muted" style="margin-top:6px;">Upload and manage your documents securely (.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt)</div>
            </div>
            <form method="get" class="search-row">
                <div class="search-box" title="Search">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search name, tags, or notes...">
                </div>
                <div class="table-actions">
                    <button type="button" class="btn" onclick="openModal('addModal')">+ Add Document</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-top">
                <div class="meta"><?= $docs->num_rows ?> items • Sorted by <strong><?= htmlspecialchars($_GET['sort'] ?? 'Newest') ?></strong></div>
                <div class="table-actions">
                    <form method="get">
                        <input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                        <label class="muted nowrap">
                            Sort:
                            <select name="sort" onchange="this.form.submit()">
                                <option <?= (!isset($_GET['sort']) || $_GET['sort'] == 'Name') ? 'selected' : '' ?>>Name</option>
                                <option <?= (isset($_GET['sort']) && $_GET['sort'] == 'Date') ? 'selected' : '' ?>>Date</option>
                                <option <?= (isset($_GET['sort']) && $_GET['sort'] == 'Size') ? 'selected' : '' ?>>Size</option>
                            </select>
                        </label>
                    </form>
                </div>
            </div>

            <div style="overflow:auto;">
                <table class="doc-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Tags</th>
                            <th>Notes</th>
                            <th>Date Added</th>
                            <th style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $docs->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= $row['size'] ?></td>
                                <td>
                                    <?php foreach (explode(',', $row['tags']) as $tag): ?>
                                        <?php if (trim($tag) != ""): ?>
                                            <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td><?= htmlspecialchars($row['notes']) ?></td>
                                <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                                <td class="action-icons">
                                    <a href="<?= $row['file_path'] ?>" target="_blank"><i class="fa fa-eye" title="View"></i></a>
                                    <a href="<?= $row['file_path'] ?>" download><i class="fa fa-download" title="Download"></i></a>
                                    <a href="?edit=<?= $row['id'] ?>"><i class="fa fa-pen" title="Edit"></i></a>
                                    <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this document?')"><i class="fa fa-trash delete" title="Delete"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Modal -->
        <div class="modal-backdrop" id="addModal">
            <div class="modal">
                <h3>Add Document</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label>File</label>
                            <input type="file" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tags</label>
                            <input type="text" name="tags" placeholder="comma, separated, tags">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn secondary" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" name="save" class="btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <!-- Edit Modal -->
    <div class="modal-backdrop" id="editModal" style="<?= $editDoc ? 'display:flex;' : 'display:none;' ?>">
        <div class="modal">
            <h3>Edit Document</h3>
            <form method="post">
                <input type="hidden" name="id" value="<?= $editDoc['id'] ?? '' ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>File Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editDoc['name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tags</label>
                        <input type="text" name="tags" value="<?= htmlspecialchars($editDoc['tags'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"><?= htmlspecialchars($editDoc['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn secondary" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                    <button type="submit" name="update" class="btn">Update</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openModal(id) {
            document.getElementById(id).style.display = "flex";
        }

        function closeModal(id) {
            document.getElementById(id).style.display = "none";
        }
    </script>

</body>

</html>