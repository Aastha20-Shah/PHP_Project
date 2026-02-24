<?php
session_start();
include 'config.php';      // DB connection
include 'lock_check.php';  // Lock/session check
requireLock();
include 'auth_check.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = intval($_SESSION['user_id']);

// --- Get user info ---
$stmt = $conn->prepare("SELECT password_manager_enabled FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pm_enabled = $user && $user['password_manager_enabled'] ? true : false;

// --- Total Passwords ---
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM passwords WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalPasswords = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

// --- Security Score ---
$securityScore = $pm_enabled ? 100 : 50; // Example: full score if PM enabled

// --- Active Sessions ---
$stmt = $conn->prepare("SELECT COUNT(*) AS active_sessions FROM sessions WHERE user_id=? AND last_active >= NOW() - INTERVAL 30 MINUTE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activeSessions = $stmt->get_result()->fetch_assoc()['active_sessions'] ?? 0;
$stmt->close();

// --- Fetch passwords table ---
$sql = "SELECT id, website, url, username, password, tag, updated 
        FROM passwords WHERE user_id=? ORDER BY updated DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$passwordsResult = $stmt->get_result();
$stmt->close();

$searchQuery = '';
$sqlParams = [];

if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
  $searchQuery = '%' . trim($_GET['q']) . '%';
  $sql = "SELECT id, website, url, username, password, tag, updated 
            FROM passwords 
            WHERE user_id=? AND (website LIKE ? OR username LIKE ? OR tag LIKE ?)
            ORDER BY updated DESC";
} else {
  $sql = "SELECT id, website, url, username, password, tag, updated 
            FROM passwords WHERE user_id=? ORDER BY updated DESC";
}

$stmt = $conn->prepare($sql);

if (!empty($searchQuery)) {
  $stmt->bind_param("isss", $user_id, $searchQuery, $searchQuery, $searchQuery);
} else {
  $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$passwordsResult = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KeyVault - Dashboard</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --sidebar-accent: #5d78ff;
      --content-bg: #f8f9fc;
      --card-bg: #ffffff;
      --text-dark: #2d3748;
      --text-light: #718096;
      --success: #4ade80;
      --warning: #facc15;
      --transition: all 0.3s ease;
    }

    body {
      margin: 0;
      background: var(--content-bg);
      font-family: 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
    }

    .main-content {
      padding: 40px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
    }

    .page-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .search-box {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 10px;
      padding: 10px 16px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      width: 280px;
    }

    .search-box i {
      color: var(--text-light);
      margin-right: 10px;
    }

    .search-box input {
      border: none;
      outline: none;
      font-size: 15px;
      width: 100%;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 40px;
    }

    .stat-card {
      background: var(--card-bg);
      border-radius: 14px;
      padding: 20px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
      transition: var(--transition);
    }

    .stat-card:hover {
      transform: translateY(-6px);
    }

    .stat-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 12px;
    }

    .stat-title {
      font-size: 14px;
      color: var(--text-light);
    }

    .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: var(--text-dark);
    }

    .stat-change {
      font-size: 14px;
      font-weight: 600;
      color: var(--success);
    }

    .stat-change.negative {
      color: #f87171;
    }

    .stat-icon {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }

    .icon-blue {
      background: rgba(93, 120, 255, 0.12);
      color: var(--sidebar-accent);
    }

    .icon-green {
      background: rgba(74, 222, 128, 0.12);
      color: var(--success);
    }

    .icon-orange {
      background: rgba(250, 204, 21, 0.12);
      color: var(--warning);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-bg);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    }

    th,
    td {
      padding: 12px 16px;
      text-align: left;
      font-size: 14px;
    }

    th {
      background: rgba(93, 120, 255, 0.05);
      text-transform: uppercase;
      color: var(--text-light);
      font-weight: 600;
    }

    tr:hover {
      background: rgba(93, 120, 255, 0.05);
    }

    .tag {
      padding: 4px 10px;
      background: #eee;
      border-radius: 999px;
      font-size: 12px;
      text-transform: capitalize;
    }

    .pw {
      font-family: monospace;
    }

    .action-icons i {
      margin-right: 10px;
      cursor: pointer;
      color: var(--text-light);
      transition: 0.2s;
    }

    .action-icons i:hover {
      color: var(--sidebar-accent);
      transform: scale(1.1);
    }
  </style>
</head>

<body>
  <div class="main-content">
    <div class="header">
      <h1 class="page-title">Dashboard Overview</h1>
      <div class="search-box">
        <form method="get" style="width:100%; display:flex;">
          <input type="text" name="q" placeholder="Search vault items..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
          <button type="submit" style="display:none;"></button>
        </form>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-title">Total Passwords</div>
          <div class="stat-icon icon-blue"><i class="fas fa-key"></i></div>
        </div>
        <div class="stat-value"><?= $totalPasswords ?></div>
        <div class="stat-change"><?= "+" . ($totalPasswords - ($_SESSION['prev_password_count'] ?? $totalPasswords)) ?> since last check</div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-title">Security Score</div>
          <div class="stat-icon icon-green"><i class="fas fa-shield-alt"></i></div>
        </div>
        <div class="stat-value"><?= $securityScore ?>%</div>
        <div class="stat-change"><?= $securityScore >= 90 ? "Excellent protection" : "Needs improvement" ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-title">Active Sessions</div>
          <div class="stat-icon icon-orange"><i class="fas fa-user-clock"></i></div>
        </div>
        <div class="stat-value"><?= $activeSessions ?></div>
        <div class="stat-change"><?= $activeSessions ?> devices</div>
      </div>
    </div>

    <!-- Passwords Table -->
    <table>
      <thead>
        <tr>
          <th>Website</th>
          <th>Username</th>
          <th>Password</th>
          <th>Tag</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($passwordsResult->num_rows == 0): ?>
          <tr>
            <td colspan="6" style="text-align:center;color:#888;">No passwords stored</td>
          </tr>
        <?php else: ?>
          <?php while ($row = $passwordsResult->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['website']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td class="pw">••••••••</td>
              <td><span class="tag"><?= htmlspecialchars($row['tag']) ?></span></td>
              <td><?= htmlspecialchars($row['updated']) ?></td>
              <td class="action-icons">
                <a href="password.php?show=<?= $row['id'] ?>" title="Show"><i class="fas fa-lock"></i></a>
                <a href="password.php?copy=<?= $row['id'] ?>" title="Copy"><i class="fas fa-copy"></i></a>
                <a href="password.php?edit=<?= $row['id'] ?>" title="Edit"><i class="fas fa-pen"></i></a>
                <a href="password.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this password?')" title="Delete"><i class="fas fa-trash"></i></a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <?php $_SESSION['prev_password_count'] = $totalPasswords; ?>

  </div>
</body>

</html>