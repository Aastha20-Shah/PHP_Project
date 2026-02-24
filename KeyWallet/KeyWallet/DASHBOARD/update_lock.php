<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$userId = $_SESSION['user_id'];
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

if ($status === 1) {
    if (empty($_POST['password']) || empty($_POST['confirm_password'])) {
        echo json_encode(["success" => false, "message" => "Both password fields required"]);
        exit();
    }

    if ($_POST['password'] !== $_POST['confirm_password']) {
        echo json_encode(["success" => false, "message" => "Passwords do not match"]);
        exit();
    }

    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE users SET website_lock_enabled=1, website_lock_password=? WHERE id=?");
    $stmt->bind_param("si", $password, $userId);
    $success = $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE users SET website_lock_enabled=0, website_lock_password=NULL WHERE id=?");
    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    $stmt->close();
}

if ($success) {
    header("Location: securitytools.php"); // redirect after success
    exit();
} else {
    echo "Error storing password!";
}
