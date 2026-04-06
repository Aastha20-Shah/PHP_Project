<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$msg = '';
$msg_type = 'success';
if (isset($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
  $msg_type = (string)($_GET['type'] ?? 'success');
  if (!in_array($msg_type, ['success', 'danger', 'warning', 'info'], true)) {
    $msg_type = 'success';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'delete_message') {
    $message_id = (int)($_POST['message_id'] ?? 0);

    if ($message_id <= 0) {
      header('Location: contact_messages.php?msg=' . urlencode('Invalid message.') . '&type=' . urlencode('danger'));
      exit;
    }

    $stmt = $conn->prepare('DELETE FROM contact_messages WHERE id = ?');
    if (!$stmt) {
      header('Location: contact_messages.php?msg=' . urlencode('Server error. Please try again.') . '&type=' . urlencode('danger'));
      exit;
    }

    $stmt->bind_param('i', $message_id);
    $stmt->execute();
    $affected = (int)$stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
      header('Location: contact_messages.php?msg=' . urlencode('Message deleted.') . '&type=' . urlencode('success'));
      exit;
    }

    header('Location: contact_messages.php?msg=' . urlencode('Message not found or already deleted.') . '&type=' . urlencode('warning'));
    exit;
  }
}

$messages = [];
$res = $conn->query(
  "SELECT id, name, email, message, created_at\n     FROM contact_messages\n    ORDER BY created_at DESC, id DESC\n    LIMIT 500"
);
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $messages[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messages | Admin</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../custom_style.css?v=<?php echo $css_v; ?>">
</head>

<body style="background-color: var(--secondary-color);">
  <nav class="navbar navbar-expand-lg bg-white py-3 shadow-sm">
    <div class="container">
      <a class="navbar-brand text-primary" href="dashboard.php"><i class="fas fa-shield-halved me-2"></i>Medkit Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="doctors.php">Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="patients.php">Patients</a></li>
          <li class="nav-item"><a class="nav-link" href="verify_doctors.php">Verify Doctors</a></li>
          <li class="nav-item"><a class="nav-link" href="commissions.php">Commissions</a></li>
          <li class="nav-item"><a class="nav-link active" href="contact_messages.php">Messages</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_users.php">Admins</a></li>
        </ul>
        <div class="d-flex align-items-center gap-3">
          <span class="text-muted small"><i class="fas fa-user me-1"></i><?php echo admin_h(admin_name()); ?></span>
          <a class="btn btn-outline-primary btn-sm" href="logout.php">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h3 class="fw-bold mb-0">Contact Messages</h3>
        <div class="text-muted small">Messages submitted from the Contact Us page.</div>
      </div>
      <span class="badge bg-primary"><?php echo count($messages); ?></span>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="fw-bold"><i class="fas fa-inbox me-2 text-primary"></i>Inbox</div>
          <div class="small text-muted">Latest first</div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 180px;">Date</th>
              <th style="width: 180px;">Name</th>
              <th style="width: 220px;">Email</th>
              <th>Message</th>
              <th class="text-end" style="width: 120px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($messages)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">No messages yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($messages as $m): ?>
                <?php
                $id = (int)($m['id'] ?? 0);
                $created_raw = (string)($m['created_at'] ?? '');
                $created_ts = $created_raw !== '' ? strtotime($created_raw) : false;
                $created_label = $created_ts ? date('M j, Y g:i A', $created_ts) : '';

                $name = (string)($m['name'] ?? '');
                $email = (string)($m['email'] ?? '');
                $message = (string)($m['message'] ?? '');
                ?>
                <tr>
                  <td class="text-muted small text-nowrap"><?php echo admin_h($created_label); ?></td>
                  <td class="fw-semibold"><?php echo admin_h($name); ?></td>
                  <td>
                    <a class="text-decoration-none" href="mailto:<?php echo admin_h($email); ?>"><?php echo admin_h($email); ?></a>
                  </td>
                  <td>
                    <div class="small bg-body-tertiary border rounded-3 p-2 text-break" style="white-space: pre-wrap;"><?php echo admin_h($message); ?></div>
                  </td>
                  <td class="text-end">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this message?');">
                      <input type="hidden" name="action" value="delete_message">
                      <input type="hidden" name="message_id" value="<?php echo (int)$id; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash me-1"></i>Delete
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>