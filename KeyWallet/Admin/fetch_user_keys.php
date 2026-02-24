<?php
session_start();
require_once 'config.php';

// Only allow logged-in admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Response array
$response = [
    'passwords' => [],
    'documents' => [],
    'paymentcards' => [],
    'media' => []
];

// Fetch Passwords
$res = mysqli_query($conn, "SELECT * FROM passwords WHERE user_id = $user_id ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) $response['passwords'][] = $row;

// Fetch Documents
$res = mysqli_query($conn, "SELECT * FROM documents WHERE user_id = $user_id ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) $response['documents'][] = $row;

// Fetch Payment Cards
$res = mysqli_query($conn, "SELECT * FROM payment_cards WHERE user_id = $user_id ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) $response['paymentcards'][] = $row;

$res = mysqli_query($conn, "SELECT * FROM media WHERE user_id = $user_id ORDER BY id DESC");
while ($row = mysqli_fetch_assoc($res)) $response['media'][] = $row;

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
