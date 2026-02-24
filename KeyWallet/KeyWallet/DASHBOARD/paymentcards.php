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
$user_id = intval($_SESSION['user_id']);

// --- Handle Add ---
if (isset($_POST['add'])) {
    $holder = $conn->real_escape_string($_POST['holder']);
    $brand  = $conn->real_escape_string($_POST['brand']);
    // Store only the card number, not the CVV
    $number = $conn->real_escape_string($_POST['card_number']);
    $expiry = $conn->real_escape_string($_POST['expiry']);
    $note   = $conn->real_escape_string($_POST['note']);

    // Removed `cvv` column from the INSERT statement
    $conn->query("INSERT INTO payment_cards (user_id, holder, brand, card_number, expiry, note, updated_at) 
                     VALUES ($user_id,'$holder','$brand','$number','$expiry','$note',NOW())");
    logEvent($conn, $user_id, $_SESSION['user_name'], "Added payment card ($brand - ****" . substr($number, -4) . ")");

    header("Location: paymentcards.php");
    exit;
}

// --- Handle Delete ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT brand, card_number FROM payment_cards WHERE id=$id AND user_id=$user_id");
    $card = $res ? $res->fetch_assoc() : null;
    $conn->query("DELETE FROM payment_cards WHERE id=$id AND user_id=$user_id");
    if ($card) {
        logEvent(
            $conn,
            $user_id,
            $_SESSION['user_name'],
            "Deleted payment card ({$card['brand']} - ****" . substr($card['card_number'], -4) . ")"
        );
    } else {
        logEvent($conn, $user_id, $_SESSION['user_name'], "Deleted payment card (ID: $id)");
    }

    header("Location: paymentcards.php"); // Corrected to paymentcards.php
    exit;
}

// --- Handle Update ---
if (isset($_POST['update'])) {
    $id     = intval($_POST['id']);
    $holder = $conn->real_escape_string($_POST['holder']);
    $brand  = $conn->real_escape_string($_POST['brand']);
    // Removed CVV from the update process
    $number = $conn->real_escape_string($_POST['card_number']);
    $expiry = $conn->real_escape_string($_POST['expiry']);
    $note   = $conn->real_escape_string($_POST['note']);

    // Removed `cvv` from the UPDATE statement
    $conn->query("UPDATE payment_cards SET 
                     holder='$holder', brand='$brand', card_number='$number', 
                     expiry='$expiry', note='$note', updated_at=NOW() 
                 WHERE id=$id AND user_id=$user_id");
    logEvent($conn, $user_id, $_SESSION['user_name'], "Updated payment card ($brand - ****" . substr($number, -4) . ")");

    header("Location: paymentcards.php");
    exit;
}

// --- Handle Search & Sort ---
$search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sort   = isset($_GET['sort']) ? $conn->real_escape_string($_GET['sort']) : 'brand';
if (!in_array($sort, ['brand', 'holder', 'updated_at'])) $sort = 'brand';

$sql = "SELECT * FROM payment_cards 
          WHERE user_id=$user_id
          AND (holder LIKE '%$search%' 
               OR brand LIKE '%$search%' 
               OR card_number LIKE '%$search%' 
               OR note LIKE '%$search%')
          ORDER BY $sort ASC";
$result = $conn->query($sql);
$count = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Payment Cards - KeyVault</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --content-bg: #f8f9fc;
            --card-bg: #ffffff;
            --text-dark: #2d3748;
            --text-light: #718096;
            --sidebar-accent: #5d78ff;
            --danger: #f87171;
            --transition: all .22s cubic-bezier(.175, .885, .32, 1.275);
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
            gap: 8px;
            align-items: center;
            background: var(--glass);
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            color: var(--text-light);
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

        /* Hide CVV input field for security */
        input[name="cvv"] {
            display: none;
        }

        input[type="text"],
        input[type="password"],
        input[type="url"],
        input[type="month"] {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e6e9ef;
            outline: none;
            font-size: 14px;
        }

        input:focus {
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
                <h1 class="page-title">Payment Cards</h1>
                <div class="muted" style="margin-top:6px;">Manage credit/debit cards securely</div>
            </div>
            <form method="get" class="search-row">
                <div class="search-box">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by cardholder, brand or last digits...">
                </div>
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <div class="table-actions">
                    <button type="button" class="btn" onclick="document.getElementById('addModal').style.display='flex'">+ Add Card</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-top">
                <div class="meta"><?= $count ?> cards • Sorted by <strong><?= ucfirst(str_replace('_', ' ', $sort)) ?></strong></div>
                <div class="table-actions">
                    <form method="get" id="sortForm">
                        <label class="muted nowrap">
                            Sort:
                            <select name="sort" onchange="this.form.submit()" form="sortForm">
                                <option value="brand" <?= $sort == 'brand' ? 'selected' : '' ?>>Brand</option>
                                <option value="holder" <?= $sort == 'holder' ? 'selected' : '' ?>>Cardholder</option>
                                <option value="updated_at" <?= $sort == 'updated_at' ? 'selected' : '' ?>>Last Updated</option>
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
                            <th>Cardholder</th>
                            <th>Brand</th>
                            <th>Card Number</th>

                            <th>Expiry</th>
                            <th>Note</th>
                            <th>Last Updated</th>
                            <th style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($count == 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#888;padding:20px;">No cards found</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700"><?= htmlspecialchars($row['holder']) ?></div>
                                        <div class="muted"><?= htmlspecialchars($row['brand']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['brand']) ?></td>
                                    <td><?= "**** **** **** " . substr($row['card_number'], -4) ?></td>
                                    <td><?= htmlspecialchars($row['expiry']) ?></td>
                                    <td><?= htmlspecialchars($row['note']) ?></td>
                                    <td><?= htmlspecialchars($row['updated_at']) ?></td>
                                    <td class="action-icons">
                                        <a href="#" onclick="openEditModal(<?= $row['id'] ?>,'<?= htmlspecialchars($row['holder']) ?>','<?= htmlspecialchars($row['brand']) ?>','<?= htmlspecialchars($row['card_number']) ?>','<?= htmlspecialchars($row['expiry']) ?>','<?= htmlspecialchars($row['note']) ?>')" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        <a href="paymentcards.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this card?')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div class="modal-backdrop" id="addModal">
        <div class="modal">
            <h3>Add Card</h3>
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Cardholder</label>
                        <input type="text" name="holder" required>
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" name="card_number" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry</label>
                        <input type="month" name="expiry" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="display:none;">
                        <label>CVV</label>
                        <input type="text" name="cvv" readonly>
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label>Note</label>
                        <input type="text" name="note">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn secondary" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
                    <button type="submit" name="add" class="btn">Save</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal-backdrop" id="editModal">
        <div class="modal">
            <h3>Edit Card</h3>
            <form method="post">
                <input type="hidden" name="id" id="edit-id">
                <div class="form-row">
                    <div class="form-group">
                        <label>Cardholder</label>
                        <input type="text" name="holder" id="edit-holder" required>
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" id="edit-brand" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" name="card_number" id="edit-card_number" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry</label>
                        <input type="month" name="expiry" id="edit-expiry" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="display:none;">
                        <label>CVV</label>
                        <input type="text" name="cvv" readonly>
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label>Note</label>
                        <input type="text" name="note" id="edit-note">
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
        // Close modal if clicking outside
        window.onclick = function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                document.getElementById('addModal').style.display = "none";
                document.getElementById('editModal').style.display = "none";
            }
        }

        // Function to populate edit modal (CVV is now removed from here)
        function openEditModal(id, holder, brand, number, expiry, note) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-holder').value = holder;
            document.getElementById('edit-brand').value = brand;
            document.getElementById('edit-card_number').value = number;
            document.getElementById('edit-expiry').value = expiry;
            document.getElementById('edit-note').value = note;
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>
</body>

</html>