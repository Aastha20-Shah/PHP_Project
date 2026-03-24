<?php
session_start();
include("config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$category_id = $_GET['category_id'] ?? null;

if (!$category_id) {
    echo json_encode(['success' => false, 'message' => 'Category ID required']);
    exit;
}

$query = "SELECT id, doctor_speciality FROM speciality WHERE category_id = ? ORDER BY doctor_speciality ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$specialities = [];
while ($row = $result->fetch_assoc()) {
    $specialities[] = $row;
}

echo json_encode(['success' => true, 'specialities' => $specialities]);
