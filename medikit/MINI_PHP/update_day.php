<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include("config.php");

function json_response(string $status, string $message = '', int $httpCode = 200): void
{
  http_response_code($httpCode);
  $payload = ['status' => $status];
  if ($message !== '') {
    $payload['message'] = $message;
  }
  echo json_encode($payload);
  exit;
}

function normalize_time_string(string $time): ?string
{
  $time = trim($time);

  if ($time === '') {
    return null;
  }

  // Accept HH:MM or HH:MM:SS
  if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    return null;
  }

  $parts = explode(':', $time);
  $hour = (int)($parts[0] ?? -1);
  $minute = (int)($parts[1] ?? -1);
  $second = (int)($parts[2] ?? 0);

  if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
    return null;
  }

  return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

if (!isset($_SESSION['doctor_id'])) {
  json_response('error', 'Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response('error', 'Invalid request method.', 405);
}

$doctor_id = (int)$_SESSION['doctor_id'];
$day_num = isset($_POST['day_num']) ? (int)$_POST['day_num'] : 0;
$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$start_time_raw = isset($_POST['start_time']) ? (string)$_POST['start_time'] : '';
$end_time_raw = isset($_POST['end_time']) ? (string)$_POST['end_time'] : '';

if ($day_num < 1 || $day_num > 7) {
  json_response('error', 'Invalid day.', 422);
}

if ($action !== 'add' && $action !== 'delete') {
  json_response('error', 'Invalid action.', 422);
}

$start_time = normalize_time_string($start_time_raw);
$end_time = normalize_time_string($end_time_raw);

if ($start_time === null || $end_time === null) {
  json_response('error', 'Invalid time format.', 422);
}

$start_ts = strtotime('1970-01-01 ' . $start_time);
$end_ts = strtotime('1970-01-01 ' . $end_time);

if ($start_ts === false || $end_ts === false) {
  json_response('error', 'Invalid time values.', 422);
}

if ($end_ts <= $start_ts) {
  json_response('error', 'End time must be after start time.', 422);
}

// Find day_id for this doctor/day
$day_id = null;
$stmt = $conn->prepare('SELECT id FROM doctor_available_day WHERE doctor_id = ? AND day = ? LIMIT 1');
if (!$stmt) {
  json_response('error', 'Database error.', 500);
}
$stmt->bind_param('ii', $doctor_id, $day_num);
$stmt->execute();
$stmt->bind_result($day_id_result);
if ($stmt->fetch()) {
  $day_id = (int)$day_id_result;
}
$stmt->close();

if ($day_id === null && $action === 'add') {
  $stmt = $conn->prepare('INSERT INTO doctor_available_day (doctor_id, day) VALUES (?, ?)');
  if (!$stmt) {
    json_response('error', 'Database error.', 500);
  }
  $stmt->bind_param('ii', $doctor_id, $day_num);
  if (!$stmt->execute()) {
    $stmt->close();
    json_response('error', 'Could not add day.', 500);
  }
  $stmt->close();
  $day_id = (int)$conn->insert_id;
}

// Nothing to delete if the day row doesn't exist
if ($day_id === null) {
  json_response('success');
}

if ($action === 'add') {
  // Insert if not exists
  $existing_id = null;
  $stmt = $conn->prepare('SELECT id FROM doctor_available_time WHERE day_id = ? AND start_time = ? AND end_time = ? LIMIT 1');
  if (!$stmt) {
    json_response('error', 'Database error.', 500);
  }
  $stmt->bind_param('iss', $day_id, $start_time, $end_time);
  $stmt->execute();
  $stmt->bind_result($existing_id_result);
  if ($stmt->fetch()) {
    $existing_id = (int)$existing_id_result;
  }
  $stmt->close();

  if ($existing_id === null) {
    $stmt = $conn->prepare('INSERT INTO doctor_available_time (day_id, start_time, end_time) VALUES (?, ?, ?)');
    if (!$stmt) {
      json_response('error', 'Database error.', 500);
    }
    $stmt->bind_param('iss', $day_id, $start_time, $end_time);
    if (!$stmt->execute()) {
      $stmt->close();
      json_response('error', 'Could not add time.', 500);
    }
    $stmt->close();
  }

  json_response('success');
}

// action === 'delete'
$stmt = $conn->prepare('DELETE FROM doctor_available_time WHERE day_id = ? AND start_time = ? AND end_time = ?');
if (!$stmt) {
  json_response('error', 'Database error.', 500);
}
$stmt->bind_param('iss', $day_id, $start_time, $end_time);
$stmt->execute();
$stmt->close();

// Cleanup orphan day rows
$count = 0;
$stmt = $conn->prepare('SELECT COUNT(*) FROM doctor_available_time WHERE day_id = ?');
if (!$stmt) {
  json_response('error', 'Database error.', 500);
}
$stmt->bind_param('i', $day_id);
$stmt->execute();
$stmt->bind_result($count_result);
$stmt->fetch();
$count = (int)$count_result;
$stmt->close();

if ($count === 0) {
  $stmt = $conn->prepare('DELETE FROM doctor_available_day WHERE id = ? AND doctor_id = ?');
  if ($stmt) {
    $stmt->bind_param('ii', $day_id, $doctor_id);
    $stmt->execute();
    $stmt->close();
  }
}

json_response('success');
