<?php
require_once __DIR__ . '/_bootstrap.php';
admin_require_login();

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
if ($doctor_id <= 0) {
  http_response_code(400);
  echo 'Invalid doctor.';
  exit;
}

$stmt = $conn->prepare('SELECT license_document FROM users WHERE id = ? AND role_id = 2 LIMIT 1');
if (!$stmt) {
  http_response_code(500);
  echo 'Unable to fetch license.';
  exit;
}
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$rel = (string)($row['license_document'] ?? '');
$rel = str_replace(["\\", "\0"], ['/', ''], $rel);
$rel = ltrim($rel, '/');

if ($rel === '' || strpos($rel, 'uploads/licenses/') !== 0) {
  http_response_code(404);
  echo 'License document not found.';
  exit;
}

$baseDir = realpath(__DIR__ . '/../uploads/licenses');
$fileAbs = realpath(__DIR__ . '/../' . $rel);
if ($baseDir === false || $fileAbs === false) {
  http_response_code(404);
  echo 'License document not found.';
  exit;
}

$baseDir = str_replace('\\', '/', $baseDir) . '/';
$fileAbsNorm = str_replace('\\', '/', $fileAbs);
if (substr($fileAbsNorm, 0, strlen($baseDir)) !== $baseDir) {
  http_response_code(403);
  echo 'Access denied.';
  exit;
}

if (!is_file($fileAbs) || !is_readable($fileAbs)) {
  http_response_code(404);
  echo 'License document not found.';
  exit;
}

$ext = strtolower(pathinfo($fileAbs, PATHINFO_EXTENSION));
$allowed = [
  'pdf' => 'application/pdf',
  'jpg' => 'image/jpeg',
  'jpeg' => 'image/jpeg',
  'png' => 'image/png',
  'webp' => 'image/webp',
];
if (!isset($allowed[$ext])) {
  http_response_code(415);
  echo 'Unsupported file type.';
  exit;
}

$mime = $allowed[$ext];
$filename = basename($fileAbs);
$filenameSafe = str_replace(["\r", "\n", '"'], '', $filename);

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . $filenameSafe . '"');
header('Content-Length: ' . filesize($fileAbs));

readfile($fileAbs);
exit;
