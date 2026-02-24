<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) die("Not logged in");

$user_id = $_SESSION['user_id'];
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

$stmt = $conn->prepare("UPDATE users SET password_manager_enabled=? WHERE id=?");
$stmt->bind_param("ii", $status, $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
