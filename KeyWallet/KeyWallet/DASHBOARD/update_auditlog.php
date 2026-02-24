<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit("error");
}

$userId = intval($_SESSION['user_id']);
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

// Update audit_log_enabled
$stmt = $conn->prepare("UPDATE users SET audit_log_enabled = ? WHERE id = ?");
$stmt->bind_param("ii", $status, $userId);

if ($stmt->execute()) {
    echo "success"; // only success
} else {
    echo "error";
}

$stmt->close();
$conn->close();
