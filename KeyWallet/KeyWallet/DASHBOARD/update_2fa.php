<?php
session_start();
include 'config.php'; // adjust path if needed

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

if (isset($_POST['status'])) {
    $status = intval($_POST['status']);
    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $userId);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }

    $stmt->close();
}
?>
