<?php
session_start(); 
include("config.php");

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$user_id = intval($_SESSION['user_id']);

$sql = "SELECT COUNT(*) AS total FROM passwords WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo $row['total'];
$stmt->close();
$conn->close();
?>
