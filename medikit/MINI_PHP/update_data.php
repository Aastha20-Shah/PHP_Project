<?php
session_start();
include("config.php");
header('Content-Type: application/json');

if (!isset($_SESSION['doctor_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$action = $_POST['action'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$day_num = intval($_POST['day_num'] ?? 0);

if (!$day_num || !$start_time || !$end_time || !$action) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM doctor_available_day WHERE doctor_id = ? AND day = ?");
$stmt->bind_param("ii", $doctor_id, $day_num);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $day_id = $result->fetch_assoc()['id'];
} else {
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO doctor_available_day (doctor_id, day) VALUES (?, ?)");
        $stmt->bind_param("ii", $doctor_id, $day_num);
        $stmt->execute();
        $day_id = $stmt->insert_id;
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Nothing to delete.']);
        exit;
    }
}

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT INTO doctor_available_time (day_id, start_time, end_time) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $day_id, $start_time, $end_time);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to add time. It may already exist.']);
    }
} elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM doctor_available_time WHERE day_id = ? AND start_time = ? AND end_time = ?");
    $stmt->bind_param("iss", $day_id, $start_time, $end_time);
    $stmt->execute();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM doctor_available_time WHERE day_id = ?");
    $stmt->bind_param("i", $day_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['count'] == 0) {
        $stmt = $conn->prepare("DELETE FROM doctor_available_day WHERE id = ?");
        $stmt->bind_param("i", $day_id);
        $stmt->execute();
    }
    echo json_encode(['status' => 'success']);
}
$conn->close();