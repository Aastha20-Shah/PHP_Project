<?php

if (!function_exists('medikit_ensure_profile_image_schema')) {
  function medikit_ensure_profile_image_schema($conn): void
  {
    if (!($conn instanceof mysqli)) {
      return;
    }

    $res = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_image'");
    if ($res && $res->num_rows === 0) {
      // Store relative path (e.g., uploads/doctors/xyz.jpg)
      $conn->query("ALTER TABLE `users` ADD COLUMN `profile_image` VARCHAR(255) NULL DEFAULT NULL");
    }
  }
}

if (!function_exists('medikit_profile_image_base_dir')) {
  function medikit_profile_image_base_dir(): string
  {
    return rtrim(str_replace('\\', '/', __DIR__), '/') . '/uploads/doctors';
  }
}

if (!function_exists('medikit_profile_image_rel_path')) {
  function medikit_profile_image_rel_path(string $filename): string
  {
    $filename = str_replace(['\\', "\0"], ['/', ''], $filename);
    $filename = ltrim($filename, '/');
    return 'uploads/doctors/' . $filename;
  }
}

if (!function_exists('medikit_profile_image_abs_from_rel')) {
  function medikit_profile_image_abs_from_rel(string $relativePath): string
  {
    $relativePath = str_replace(['\\', "\0"], ['/', ''], $relativePath);
    $relativePath = ltrim($relativePath, '/');
    return rtrim(str_replace('\\', '/', __DIR__), '/') . '/' . $relativePath;
  }
}

if (!function_exists('medikit_profile_image_is_within_uploads')) {
  function medikit_profile_image_is_within_uploads(string $absPath): bool
  {
    $base = realpath(medikit_profile_image_base_dir());
    $real = realpath($absPath);
    if ($base === false || $real === false) {
      return false;
    }
    $base = str_replace('\\', '/', $base);
    $real = str_replace('\\', '/', $real);
    $prefix = $base . '/';
    return substr($real, 0, strlen($prefix)) === $prefix;
  }
}

if (!function_exists('medikit_doctor_profile_image_upload')) {
  function medikit_doctor_profile_image_upload($conn, int $doctorId, array $file): array
  {
    medikit_ensure_profile_image_schema($conn);

    if ($doctorId <= 0) {
      return ['success' => false, 'message' => 'Invalid doctor.'];
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'message' => 'Please choose a valid image file.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      return ['success' => false, 'message' => 'Upload failed. Please try again.'];
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info['mime'])) {
      return ['success' => false, 'message' => 'Only image files are allowed.'];
    }

    $allowed = [
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp'
    ];

    $mime = (string)$info['mime'];
    if (!isset($allowed[$mime])) {
      return ['success' => false, 'message' => 'Only JPG, PNG, or WEBP images are allowed.'];
    }

    $ext = $allowed[$mime];
    $uploadDirAbs = medikit_profile_image_base_dir();

    if (!is_dir($uploadDirAbs)) {
      @mkdir($uploadDirAbs, 0755, true);
    }
    if (!is_dir($uploadDirAbs) || !is_writable($uploadDirAbs)) {
      return ['success' => false, 'message' => 'Upload folder is not writable.'];
    }

    try {
      $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
      $rand = (string)mt_rand(100000, 999999);
    }

    $filename = 'doctor_' . $doctorId . '_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $relPath = medikit_profile_image_rel_path($filename);
    $destAbs = $uploadDirAbs . '/' . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
      return ['success' => false, 'message' => 'Failed to save image. Please try again.'];
    }

    // Remove old image (if any)
    $old = '';
    $stmtOld = $conn->prepare('SELECT profile_image FROM users WHERE id = ? LIMIT 1');
    if ($stmtOld) {
      $stmtOld->bind_param('i', $doctorId);
      $stmtOld->execute();
      $row = $stmtOld->get_result()->fetch_assoc();
      $old = (string)($row['profile_image'] ?? '');
      $stmtOld->close();
    }

    $stmtUp = $conn->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
    if (!$stmtUp) {
      // Roll back saved file if DB update fails
      @unlink($destAbs);
      return ['success' => false, 'message' => 'Database error. Please try again.'];
    }
    $stmtUp->bind_param('si', $relPath, $doctorId);
    $stmtUp->execute();
    $stmtUp->close();

    if ($old !== '') {
      $oldAbs = medikit_profile_image_abs_from_rel($old);
      if (medikit_profile_image_is_within_uploads($oldAbs) && file_exists($oldAbs)) {
        @unlink($oldAbs);
      }
    }

    return ['success' => true, 'message' => 'Profile photo updated.', 'path' => $relPath];
  }
}

if (!function_exists('medikit_doctor_profile_image_delete')) {
  function medikit_doctor_profile_image_delete($conn, int $doctorId): array
  {
    medikit_ensure_profile_image_schema($conn);

    if ($doctorId <= 0) {
      return ['success' => false, 'message' => 'Invalid doctor.'];
    }

    $old = '';
    $stmtOld = $conn->prepare('SELECT profile_image FROM users WHERE id = ? LIMIT 1');
    if ($stmtOld) {
      $stmtOld->bind_param('i', $doctorId);
      $stmtOld->execute();
      $row = $stmtOld->get_result()->fetch_assoc();
      $old = (string)($row['profile_image'] ?? '');
      $stmtOld->close();
    }

    $stmtUp = $conn->prepare('UPDATE users SET profile_image = NULL WHERE id = ?');
    if ($stmtUp) {
      $stmtUp->bind_param('i', $doctorId);
      $stmtUp->execute();
      $stmtUp->close();
    }

    if ($old !== '') {
      $oldAbs = medikit_profile_image_abs_from_rel($old);
      if (medikit_profile_image_is_within_uploads($oldAbs) && file_exists($oldAbs)) {
        @unlink($oldAbs);
      }
    }

    return ['success' => true, 'message' => 'Profile photo removed.'];
  }
}
