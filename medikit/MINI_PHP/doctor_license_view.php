<?php
session_start();

include('config.php');
include('admin_helpers.php');

if (!isset($_SESSION['doctor_id'])) {
  http_response_code(403);
  echo 'Unauthorized';
  exit;
}

$doctor_id = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctor_id <= 0) {
  http_response_code(403);
  echo 'Unauthorized';
  exit;
}

medikit_doctor_verification_ensure_schema($conn);

$stmt = $conn->prepare('SELECT license_document FROM users WHERE id = ? AND role_id = 2 LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  echo 'Server error';
  exit;
}

$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$rel = (string)($row['license_document'] ?? '');
$rel = str_replace(['\\', "\0"], ['/', ''], $rel);
$rel = ltrim($rel, '/');

if ($rel === '' || strpos($rel, 'uploads/licenses/') !== 0) {
  http_response_code(404);
  echo 'License document not found';
  exit;
}

$baseDir = realpath(__DIR__ . '/uploads/licenses');
$fileAbs = realpath(__DIR__ . '/' . $rel);

if ($baseDir === false || $fileAbs === false) {
  http_response_code(404);
  echo 'License document not found';
  exit;
}

$baseDir = str_replace('\\', '/', $baseDir);
$fileAbs = str_replace('\\', '/', $fileAbs);
$baseDir = rtrim($baseDir, '/') . '/';

if (strpos($fileAbs, $baseDir) !== 0 || !is_file($fileAbs)) {
  http_response_code(404);
  echo 'License document not found';
  exit;
}

$mime = '';
if (function_exists('finfo_open')) {
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  if ($finfo) {
    $mime = (string)finfo_file($finfo, $fileAbs);
    finfo_close($finfo);
  }
}
if ($mime === '' && function_exists('mime_content_type')) {
  $mime = (string)mime_content_type($fileAbs);
}

$allowed = [
  'application/pdf',
  'image/jpeg',
  'image/png',
  'image/webp',
];
if (!in_array($mime, $allowed, true)) {
  $mime = 'application/octet-stream';
}

$filename = basename($fileAbs);
$size = (int)@filesize($fileAbs);

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
if ($size > 0) {
  header('Content-Length: ' . $size);
}
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');

readfile($fileAbs);
exit;
