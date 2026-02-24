<?php
session_start();
include 'config.php'; // DB connection

// --- Require login to view logs ---
if (!isset($_SESSION['user_id'])) {
    exit("error");
}

$userId = intval($_SESSION['user_id']);
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

// Update audit_log_enabled
$stmt = $conn->prepare("UPDATE users SET audit_log_enabled = ? WHERE id = ?");
$stmt->bind_param("ii", $status, $userId);
if ($stmt->execute()) {
    // echo "success"; // only echo success
} else {
    echo "error";
}

// echo json_encode(["success" => true, "message" => "Audit Log setting updated"]);
// --- Fetch audit logs ---
$logsResult = $conn->prepare("SELECT * FROM audit_log WHERE user_id = ? ORDER BY created_at ASC");
$logsResult->bind_param("i", $userId);
$logsResult->execute();
$result = $logsResult->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$logsResult->close();

// --- Handle AJAX request for real-time logs ---
if (isset($_GET['fetch_logs']) && $_GET['fetch_logs'] == 1) {
    header('Content-Type: application/json');
    echo json_encode($logs);
    exit;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Audit Log - KeyVault</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --content-bg: #f8f9fc;
            --card-bg: #fff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --danger: #f87171;
            --success: #22c55e;
            --warning: #facc15;
            --transition: all 0.22s cubic-bezier(.175, .885, .32, 1.275);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            background: var(--content-bg);
            color: var(--text-dark);
        }

        .main {
            padding: 34px;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        table.audit-table {
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

        .status {
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .success {
            background: #dcfce7;
            color: var(--success);
        }

        .warning {
            background: #fef9c3;
            color: #ca8a04;
        }

        .danger {
            background: #fee2e2;
            color: var(--danger);
        }

        @media(max-width:992px) {
            .main {
                padding: 20px;
                margin: 0 12px;
            }

            table.audit-table {
                min-width: 600px;
            }
        }

        @media(max-width:700px) {
            table.audit-table {
                min-width: auto;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
    <main class="main">
        <div class="header">
            <div>
                <h1 class="page-title">Audit Log</h1>
                <div class="muted">Track user activity and security-related events across the website</div>
            </div>
        </div>

        <div class="card">
            <div class="table-top">
                <div class="meta"><span id="metaCount"><?= count($logs) ?></span> events logged</div>
            </div>
            <div style="overflow:auto;">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="auditTbody">
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['id']) ?></td>
                                <td><?= htmlspecialchars($log['user_name']) ?></td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['created_at']) ?></td>
                                <td><span class="status <?= htmlspecialchars($log['status']) ?>"><?= strtoupper($log['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($logs) == 0): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:#888;padding:20px;">No logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const tbody = document.getElementById('auditTbody');
        const metaCount = document.getElementById('metaCount');

        async function fetchLogs() {
            const resp = await fetch('?fetch_logs=1');
            const logs = await resp.json();
            tbody.innerHTML = '';
            logs.forEach(log => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${log.id}</td><td>${log.user_name}</td><td>${log.action}</td><td>${log.created_at}</td><td><span class="status ${log.status}">${log.status.toUpperCase()}</span></td>`;
                tbody.appendChild(tr);
            });
            metaCount.textContent = logs.length;
        }

        // Refresh every 5 seconds
        setInterval(fetchLogs, 5000);
    </script>
</body>

</html>