<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

function requireLock()
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.html");
        exit();
    }

    $userId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT website_lock_enabled FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($enabled);
    $stmt->fetch();
    $stmt->close();

    if ($enabled) {
        // If user has not entered lock password this session
        if (!isset($_SESSION['lock_verified']) || $_SESSION['lock_verified'] !== true) {
            $currentPage = basename($_SERVER['PHP_SELF']); // e.g., private_media.php
            header("Location: lock_auth.php?redirect=" . urlencode($currentPage));
            exit();
        }
    }
}
