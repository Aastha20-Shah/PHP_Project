<?php
session_start();
include("config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['doctor_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$doctor_id = $_SESSION['doctor_id'];
$category_id = $_POST['category_id'] ?? null;
$specialities = $_POST['specialities'] ?? [];
$clinic_name = $_POST['clinic_name'] ?? '';
$experience_years = (int)($_POST['experience_years'] ?? 0);
$education = $_POST['education'] ?? '';
$bio = $_POST['bio'] ?? '';

if (!$category_id || empty($specialities)) {
    echo json_encode(['success' => false, 'message' => 'Please select category and at least one speciality']);
    exit;
}

// Update category and history in users table
$update_query = "UPDATE users SET category_id = ?, clinic_name = ?, experience_years = ?, education = ?, bio = ? WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("isissi", $category_id, $clinic_name, $experience_years, $education, $bio, $doctor_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    exit;
}

// Delete existing specialities
$delete_query = "DELETE FROM doctor_speciality WHERE doctor_id = ?";
$stmt = $conn->prepare($delete_query);
$stmt->bind_param("i", $doctor_id);
$stmt->execute();

// Insert new specialities
$insert_query = "INSERT INTO doctor_speciality (doctor_id, speciality_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
$stmt = $conn->prepare($insert_query);

foreach ($specialities as $speciality_id) {
    $stmt->bind_param("ii", $doctor_id, $speciality_id);
    $stmt->execute();
}

echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
