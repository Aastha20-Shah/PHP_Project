<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';
include 'logger.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
