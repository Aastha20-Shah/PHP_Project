<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$userId = $_SESSION['user_id'];
$status = isset($_POST['status']) ? (int) $_POST['status'] : 0;

$sql = "UPDATE users SET rotation_enabled = $status WHERE id = $userId";
if ($conn->query($sql)) {
    echo "Rotation updated to $status";
} else {
    echo "Error: " . $conn->error;
}
