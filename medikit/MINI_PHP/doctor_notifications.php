<?php
session_start();

include('config.php');
include('doctor_notification_helpers.php');

if (!isset($_SESSION['doctor_id'])) {
  header('Location: login.php');
  exit;
}

$doctor_id = (int)($_SESSION['doctor_id'] ?? 0);

$redirect = 'doctor_dashboard.php';
if (isset($_POST['redirect'])) {
  $candidate = basename((string)$_POST['redirect']);
  if ($candidate !== '' && preg_match('/^[a-zA-Z0-9_-]+\.php$/', $candidate) && file_exists(__DIR__ . '/' . $candidate)) {
    $redirect = $candidate;
  }
}

// Optional safe query-string append (only allow numeric bill_id/booking_id)
$redirect_qs = trim((string)($_POST['redirect_qs'] ?? ''));
$safe_query = '';
if ($redirect_qs !== '') {
  if ($redirect_qs[0] === '?') {
    $redirect_qs = substr($redirect_qs, 1);
  }

  $params = [];
  parse_str($redirect_qs, $params);

  $allowed = [];
  foreach (['bill_id', 'booking_id'] as $key) {
    if (isset($params[$key])) {
      $val = (int)$params[$key];
      if ($val > 0) {
        $allowed[$key] = $val;
      }
    }
  }

  if (!empty($allowed)) {
    $safe_query = http_build_query($allowed);
  }
}

if ($safe_query !== '') {
  $redirect .= '?' . $safe_query;
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'clear_all') {
  medikit_doctor_clear_notifications($conn, $doctor_id);
}

header("Location: $redirect");
exit;
