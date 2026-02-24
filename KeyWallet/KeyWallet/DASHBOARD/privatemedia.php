<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "config.php";
include 'lock_check.php';
requireLock();
include 'auth_check.php';


if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = $_SESSION['user_id'];

// --- Handle Upload ---
if (isset($_POST['upload'])) {
    if (isset($_FILES['media']) && $_FILES['media']['error'] == 0) {
        $tags  = $conn->real_escape_string($_POST['tags']);
        $fileName = basename($_FILES['media']['name']);
        $fileTmp  = $_FILES['media']['tmp_name'];
        $fileSize = $_FILES['media']['size'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mp3', 'wav'];

        if (!in_array($fileExt, $allowed)) {
            die("❌ Invalid file type.");
        }

        $targetDir = "uploads/media/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileTarget = $targetDir . time() . "_" . $fileName;

        if (move_uploaded_file($fileTmp, $fileTarget)) {
            $sizeKB = round($fileSize / 1024) . " KB";
            $sql = "INSERT INTO media (user_id, name, size, type, tags,uploaded_at,file_path)
                    VALUES ('$user_id', '$fileName', '$sizeKB', '$fileExt', '$tags',NOW(),'$fileTarget')";
            $conn->query($sql);
            logEvent($conn, $user_id, $_SESSION['user_name'], "Uploaded media ($fileName, $sizeKB, $fileExt)");
            header("Location: privatemedia.php");
            exit;
        }
    }
}

// --- Handle Delete ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT file_path FROM media WHERE id='$id' AND user_id='$user_id'");
    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (file_exists($row['file_path'])) unlink($row['file_path']);
        logEvent($conn, $user_id, $_SESSION['user_name'], "Deleted media ({$row['name']})");
    }
    $conn->query("DELETE FROM media WHERE id='$id' AND user_id='$user_id'");
    header("Location: privatemedia.php");
    exit;
}

// --- Search & Sort ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";
$sort = isset($_GET['sort']) ? $_GET['sort'] : "name";

$orderBy = "name ASC";
if ($sort == "size") $orderBy = "CAST(size AS UNSIGNED) DESC";
if ($sort == "date") $orderBy = "date_added DESC";

$sql = "SELECT * FROM media WHERE user_id='$user_id'";
if ($search != "") {
    $sql .= " AND (name LIKE '%$search%' OR tags LIKE '%$search%')";
}
$sql .= " ORDER BY $orderBy";

$media = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Private Media - KeyVault</title>
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        body {
            background: var(--content-bg);
            color: var(--text-dark);
        }

        .main {
            padding: 34px;
            max-width: 1200px;
            margin: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
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

        .btn {
            background: var(--sidebar-accent);
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn.secondary {
            background: transparent;
            color: var(--text-dark);
            border: 1px solid #ddd;
        }

        .card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }

        .media-tile {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .media-tile:hover {
            transform: translateY(-3px);
        }

        .thumb {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f2f6;
            overflow: hidden;
        }

        .thumb img,
        .thumb video {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }

        .thumb audio {
            width: 100%;
            padding: 10px;
        }

        .meta {
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filename {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .small {
            font-size: 12px;
            color: var(--text-light);
        }

        .actions a {
            margin-left: 10px;
            color: var(--text-light);
            transition: var(--transition);
        }

        .actions a:hover {
            color: var(--sidebar-accent);
        }

        .actions .delete:hover {
            color: var(--danger);
        }

        /* Top bar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-box {
            flex: 1;
            display: flex;
            align-items: center;
            background: #fff;
            border-radius: 12px;
            padding: 6px 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .search-box input {
            border: none;
            outline: none;
            flex: 1;
            padding: 8px;
            font-size: 14px;
        }

        .sort-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fff;
        }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }

        .modal {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            width: 520px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .form-row {
            margin-bottom: 12px;
        }

        label {
            font-size: 13px;
            color: var(--text-light);
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 12px;
        }
    </style>
</head>

<body>
    <main class="main">
        <div class="header">
            <div>
                <h1 class="page-title">Private Media</h1>
                <div class="muted">Upload and manage your images, videos, gifs, and audio securely</div>
            </div>

            <!-- Search + Add -->
            <form method="get" style="display:flex;align-items:center;gap:10px;">
                <div class="search-box" style="width:260px;">
                    <i class="fa fa-search" style="margin-right:8px;color:#999;"></i>
                    <input type="text" name="search" placeholder="Search name or tags..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>
                <!-- <select name="sort" class="sort-select">
            <option value="name" <?= $sort == "name" ? "selected" : "" ?>>Name</option>
            <option value="size" <?= $sort == "size" ? "selected" : "" ?>>Size</option>
            <option value="date" <?= $sort == "date" ? "selected" : "" ?>>Date</option>
        </select>
        <button type="submit" class="btn secondary">Go</button> -->
                <button type="button" class="btn" onclick="openModal('uploadModal')">+ Add Media</button>
            </form>
        </div>

        <div class="card">
            <div class="gallery">
                <?php while ($row = $media->fetch_assoc()): ?>
                    <div class="media-tile">
                        <div class="thumb">
                            <?php if (in_array($row['type'], ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?= $row['file_path'] ?>" alt="">
                            <?php elseif (in_array($row['type'], ['mp4', 'webm'])): ?>
                                <video controls src="<?= $row['file_path'] ?>"></video>
                            <?php elseif (in_array($row['type'], ['mp3', 'wav'])): ?>
                                <audio controls src="<?= $row['file_path'] ?>"></audio>
                            <?php endif; ?>
                        </div>
                        <div class="meta">
                            <div>
                                <div class="filename"><?= htmlspecialchars($row['name']) ?></div>
                                <div class="small"><?= $row['size'] ?></div>
                            </div>
                            <div class="actions">
                                <a href="#" onclick="openViewer('<?= $row['file_path'] ?>', '<?= $row['type'] ?>'); return false;">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="<?= $row['file_path'] ?>" download><i class="fa fa-download"></i></a>
                                <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this file?')">
                                    <i class="fa fa-trash delete"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>
    <!-- Media Viewer Modal -->
    <div class="modal-backdrop" id="viewerModal">
        <div class="modal" id="viewerContent">
            <span style="float:right;cursor:pointer;" onclick="closeModal('viewerModal')">&times;</span>
            <div id="viewerMedia" style="text-align:center;"></div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal-backdrop" id="uploadModal">
        <div class="modal">
            <h3>Upload Media</h3>
            <form method="post" enctype="multipart/form-data">
                <div class="form-row">
                    <label>File</label>
                    <input type="file" name="media" accept="image/*,video/*,audio/*" required>
                </div>
                <div class="form-row">
                    <label>Tags</label>
                    <input type="text" name="tags" placeholder="comma,separated,tags">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn secondary" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" name="upload" class="btn">Upload</button>
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

        function openViewer(filePath, type) {
            const container = document.getElementById('viewerMedia');
            container.innerHTML = ''; // clear previous
            let element;

            if (['jpg', 'jpeg', 'png', 'gif'].includes(type)) {
                element = document.createElement('img');
                element.src = filePath;
                element.style.maxWidth = "100%";
                element.style.maxHeight = "80vh";
            } else if (['mp4', 'webm'].includes(type)) {
                element = document.createElement('video');
                element.src = filePath;
                element.controls = true;
                element.style.maxWidth = "100%";
                element.style.maxHeight = "80vh";
            } else if (['mp3', 'wav'].includes(type)) {
                element = document.createElement('audio');
                element.src = filePath;
                element.controls = true;
                element.style.width = "100%";
            }

            container.appendChild(element);
            document.getElementById('viewerModal').style.display = 'flex';
        }
    </script>
</body>

</html>