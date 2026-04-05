<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commission_id'], $_POST['action'])) {
  $commission_id = (int)($_POST['commission_id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');

  if ($commission_id <= 0) {
    $msg = 'Invalid commission.';
    $msg_type = 'danger';
  } elseif ($action === 'mark_paid') {
    $stmt = $conn->prepare("UPDATE doctor_commissions
            SET status = 'paid', paid_at = NOW()
            WHERE id = ? AND status = 'due'");
    if ($stmt) {
      $stmt->bind_param('i', $commission_id);
      $stmt->execute();
      $affected = $stmt->affected_rows;
      $stmt->close();

      if ($affected > 0) {
        $msg = 'Commission marked as paid.';
        $msg_type = 'success';
      } else {
        $msg = 'Commission is already paid or not found.';
        $msg_type = 'warning';
      }
    } else {
      $msg = 'Unable to update commission.';
      $msg_type = 'danger';
    }
  }

  header('Location: commissions.php?msg=' . urlencode($msg) . '&type=' . urlencode($msg_type));
  exit;
}

if (isset($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
  $msg_type = (string)($_GET['type'] ?? 'success');
  if (!in_array($msg_type, ['success', 'danger', 'warning', 'info'], true)) {
    $msg_type = 'success';
  }
}

$totals = [
  'due' => 0.0,
  'paid' => 0.0,
];
$res = $conn->query("SELECT
        COALESCE(SUM(CASE WHEN status = 'due' THEN commission_amount ELSE 0 END), 0) AS due_total,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) AS paid_total
    FROM doctor_commissions");
if ($res) {
  $row = $res->fetch_assoc();
  $totals['due'] = (float)($row['due_total'] ?? 0);
  $totals['paid'] = (float)($row['paid_total'] ?? 0);
}

$items = [];
$q = "SELECT dc.id, dc.booking_id, dc.bill_id, dc.doctor_id, dc.amount, dc.commission_percent, dc.commission_amount, dc.status, dc.created_at, dc.paid_at,
            u.firstname, u.lastname
      FROM doctor_commissions dc
      LEFT JOIN users u ON u.id = dc.doctor_id
      ORDER BY (dc.status = 'due') DESC, dc.created_at DESC, dc.id DESC
      LIMIT 200";
$res2 = $conn->query($q);
if ($res2) {
  while ($row = $res2->fetch_assoc()) {
    $items[] = $row;
  }
}

$css_v = (int)@filemtime(__DIR__ . '/../custom_style.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Commissions | Admin</title>

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
          <li class="nav-item"><a class="nav-link active" href="commissions.php">Commissions</a></li>
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
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-muted small">Commission Due</div>
                <div class="fw-bold fs-4">₹ <?php echo number_format($totals['due'], 2); ?></div>
              </div>
              <div class="text-warning fs-2"><i class="fas fa-hourglass-half"></i></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="text-muted small">Commission Paid</div>
                <div class="fw-bold fs-4">₹ <?php echo number_format($totals['paid'], 2); ?></div>
              </div>
              <div class="text-success fs-2"><i class="fas fa-circle-check"></i></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-<?php echo admin_h($msg_type); ?>" role="alert"><?php echo admin_h($msg); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 py-3">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="fw-bold mb-0">Recent Commissions</h5>
          <span class="badge bg-primary"><?php echo count($items); ?></span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Doctor</th>
              <th>Booking</th>
              <th>Bill Amount</th>
              <th>Percent</th>
              <th>Commission</th>
              <th>Status</th>
              <th>Date</th>
              <th style="min-width:120px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No commissions yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $it): ?>
                <?php
                $doctor_name = trim('Dr. ' . (string)($it['firstname'] ?? '') . ' ' . (string)($it['lastname'] ?? ''));
                if ($doctor_name === 'Dr.') {
                  $doctor_name = 'Doctor';
                }
                $status = (string)($it['status'] ?? 'due');
                $badge = ($status === 'paid') ? 'success' : 'warning';
                ?>
                <tr>
                  <td class="fw-semibold"><?php echo admin_h($doctor_name); ?></td>
                  <td>
                    <div class="small">#<?php echo (int)($it['booking_id'] ?? 0); ?></div>
                    <div class="text-muted small">Bill #<?php echo (int)($it['bill_id'] ?? 0); ?></div>
                  </td>
                  <td>₹ <?php echo number_format((float)($it['amount'] ?? 0), 2); ?></td>
                  <td><?php echo number_format((float)($it['commission_percent'] ?? 0), 2); ?>%</td>
                  <td class="fw-bold">₹ <?php echo number_format((float)($it['commission_amount'] ?? 0), 2); ?></td>
                  <td><span class="badge bg-<?php echo $badge; ?> text-uppercase"><?php echo admin_h($status); ?></span></td>
                  <td class="small text-muted"><?php echo admin_h((string)($it['created_at'] ?? '')); ?></td>
                  <td>
                    <?php if ($status === 'due'): ?>
                      <form method="POST" onsubmit="return confirm('Mark this commission as paid?');">
                        <input type="hidden" name="commission_id" value="<?php echo (int)$it['id']; ?>">
                        <button type="submit" name="action" value="mark_paid" class="btn btn-sm btn-success">
                          Mark Paid
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">Paid</span>
                    <?php endif; ?>
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