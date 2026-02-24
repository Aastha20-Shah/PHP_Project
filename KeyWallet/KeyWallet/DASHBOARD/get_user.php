<?php
session_start();
include 'auth_check.php';
include 'config.php';

$userName = 'Guest User';
$initials = 'GU';

$stmt = $conn->prepare("SELECT name, avatar FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    $userName = $user['name'];
    $avatarUrl = $user['avatar'];

    $words = explode(' ', $userName);
    $initials = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) $initials .= strtoupper(substr(end($words), 0, 1));
}

echo json_encode([
    'name' => $userName,
    'initials' => $initials,
    'avatar' => $avatarUrl
]);
