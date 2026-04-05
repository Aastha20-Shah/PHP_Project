<?php

if (!function_exists('medikit_fixed_commission_percent')) {
  function medikit_fixed_commission_percent(): float
  {
    return 5.00;
  }
}

if (!function_exists('medikit_admin_ensure_schema')) {
  function medikit_admin_ensure_schema(mysqli $conn): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL,
            `email` VARCHAR(190) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_admin_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);
  }
}

if (!function_exists('medikit_doctor_verification_ensure_schema')) {
  function medikit_doctor_verification_ensure_schema(mysqli $conn): void
  {
    $columns = [
      'license_number' => "ALTER TABLE `users` ADD COLUMN `license_number` VARCHAR(50) NULL DEFAULT NULL",
      'license_document' => "ALTER TABLE `users` ADD COLUMN `license_document` VARCHAR(255) NULL DEFAULT NULL",
      'verification_status' => "ALTER TABLE `users` ADD COLUMN `verification_status` ENUM('verified','pending','rejected') NOT NULL DEFAULT 'verified'",
      'verification_reason' => "ALTER TABLE `users` ADD COLUMN `verification_reason` VARCHAR(255) NULL DEFAULT NULL",
      'verified_at' => "ALTER TABLE `users` ADD COLUMN `verified_at` TIMESTAMP NULL DEFAULT NULL",
      'verified_by_admin_id' => "ALTER TABLE `users` ADD COLUMN `verified_by_admin_id` INT(11) NULL DEFAULT NULL",
      'commission_percent' => "ALTER TABLE `users` ADD COLUMN `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00",
    ];

    foreach ($columns as $col => $alterSql) {
      $res = $conn->query("SHOW COLUMNS FROM `users` LIKE '" . $conn->real_escape_string($col) . "'");
      if ($res && $res->num_rows === 0) {
        $conn->query($alterSql);
      }
    }
  }
}

if (!function_exists('medikit_commission_ensure_schema')) {
  function medikit_commission_ensure_schema(mysqli $conn): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS `doctor_commissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `booking_id` INT(11) NOT NULL,
            `bill_id` INT(11) NOT NULL,
            `doctor_id` INT(11) NOT NULL,
            `patient_id` INT(11) NOT NULL,
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `commission_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `status` ENUM('due','paid') NOT NULL DEFAULT 'due',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `paid_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_booking` (`booking_id`),
            KEY `idx_doctor` (`doctor_id`),
            KEY `idx_status` (`status`),
            KEY `idx_bill` (`bill_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    // Keep commissions correct:
    // - Commission applies only when booking is visited AND bill is paid.
    // - Percent is fixed (5%) and cannot be edited.
    // - Do not modify due commissions already snapped into an unpaid payment order.

    $p = number_format(medikit_fixed_commission_percent(), 2, '.', '');

    $hasPayments = false;
    $hasItems = false;
    $t1 = $conn->query("SHOW TABLES LIKE 'doctor_commission_payments'");
    if ($t1 && $t1->num_rows > 0) {
      $hasPayments = true;
    }
    $t2 = $conn->query("SHOW TABLES LIKE 'doctor_commission_payment_items'");
    if ($t2 && $t2->num_rows > 0) {
      $hasItems = true;
    }

    $lockedExclusion = '';
    if ($hasPayments && $hasItems) {
      $lockedExclusion = " AND dc.id NOT IN (\n        SELECT it.commission_id\n          FROM doctor_commission_payment_items it\n          INNER JOIN doctor_commission_payments pay ON pay.id = it.payment_id\n         WHERE pay.status = 'created'\n      )";
    }

    // Remove stale due commissions that no longer qualify (but keep locked ones intact).
    $conn->query(
      "DELETE dc\n         FROM doctor_commissions dc\n         LEFT JOIN visit_booking vb ON vb.id = dc.booking_id\n         LEFT JOIN clinic_bills cb ON cb.id = dc.bill_id\n        WHERE dc.status = 'due'\n          AND (vb.id IS NULL OR cb.id IS NULL OR vb.status <> 'visited' OR cb.payment_status <> 'paid')" .
        $lockedExclusion
    );

    // Backfill missing commissions for visited + paid bookings.
    // (INSERT IGNORE relies on uniq_booking to prevent duplicates.)
    $conn->query(
      "INSERT IGNORE INTO doctor_commissions\n            (booking_id, bill_id, doctor_id, patient_id, amount, commission_percent, commission_amount, status)\n       SELECT vb.id, cb.id, vb.doctor_id, vb.patient_id, cb.amount, {$p}, ROUND(cb.amount * {$p} / 100, 2), 'due'\n         FROM visit_booking vb\n         INNER JOIN clinic_bills cb ON cb.booking_id = vb.id\n        WHERE vb.status = 'visited'\n          AND cb.payment_status = 'paid'"
    );

    // Enforce fixed commission percent for due commissions using latest bill amount.
    // (Do not touch already paid commissions; exclude locked due commissions.)
    $where = "dc.status = 'due'\n          AND vb.status = 'visited'\n          AND cb.payment_status = 'paid'\n          AND (dc.amount <> cb.amount OR dc.commission_percent <> {$p} OR dc.commission_amount <> ROUND(cb.amount * {$p} / 100, 2))";

    if ($lockedExclusion !== '') {
      $where .= str_replace('dc.', 'dc.', $lockedExclusion);
    }

    $conn->query(
      "UPDATE doctor_commissions dc\n         INNER JOIN clinic_bills cb ON cb.id = dc.bill_id\n         INNER JOIN visit_booking vb ON vb.id = dc.booking_id\n         SET dc.amount = cb.amount,\n             dc.commission_percent = {$p},\n             dc.commission_amount = ROUND(cb.amount * {$p} / 100, 2)\n       WHERE {$where}"
    );
  }
}

if (!function_exists('medikit_commission_payments_ensure_schema')) {
  function medikit_commission_payments_ensure_schema(mysqli $conn): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS `doctor_commission_payments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `doctor_id` INT(11) NOT NULL,
            `gateway` VARCHAR(30) NOT NULL DEFAULT 'razorpay',
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` CHAR(3) NOT NULL DEFAULT 'INR',
            `razorpay_order_id` VARCHAR(100) NOT NULL,
            `razorpay_payment_id` VARCHAR(100) NULL DEFAULT NULL,
            `razorpay_signature` VARCHAR(255) NULL DEFAULT NULL,
            `razorpay_method` VARCHAR(50) NULL DEFAULT NULL,
            `status` ENUM('created','paid','failed') NOT NULL DEFAULT 'created',
            `snapshot_count` INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `paid_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_order` (`razorpay_order_id`),
            KEY `idx_doctor` (`doctor_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql);

    $sql2 = "CREATE TABLE IF NOT EXISTS `doctor_commission_payment_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `payment_id` INT(11) NOT NULL,
            `commission_id` INT(11) NOT NULL,
            `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_payment_commission` (`payment_id`, `commission_id`),
            KEY `idx_payment` (`payment_id`),
            KEY `idx_commission` (`commission_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $conn->query($sql2);
  }
}

if (!function_exists('medikit_license_upload')) {
  function medikit_license_upload(mysqli $conn, int $doctorId, array $file): array
  {
    medikit_doctor_verification_ensure_schema($conn);

    if ($doctorId <= 0) {
      return ['success' => false, 'message' => 'Invalid doctor.'];
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'message' => 'Please upload a valid license document.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      return ['success' => false, 'message' => 'Upload failed. Please try again.'];
    }

    $maxBytes = 5 * 1024 * 1024; // 5MB
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
      return ['success' => false, 'message' => 'License file must be under 5MB.'];
    }

    $mime = '';
    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime = (string)finfo_file($finfo, $tmp);
        finfo_close($finfo);
      }
    }
    if ($mime === '' && function_exists('mime_content_type')) {
      $mime = (string)mime_content_type($tmp);
    }

    $allowed = [
      'application/pdf' => 'pdf',
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
      return ['success' => false, 'message' => 'Only PDF, JPG, PNG, or WEBP files are allowed.'];
    }

    $ext = $allowed[$mime];

    $uploadDirAbs = rtrim(str_replace('\\', '/', __DIR__), '/') . '/uploads/licenses';
    if (!is_dir($uploadDirAbs)) {
      @mkdir($uploadDirAbs, 0755, true);
    }
    if (!is_dir($uploadDirAbs) || !is_writable($uploadDirAbs)) {
      return ['success' => false, 'message' => 'Upload folder is not writable.'];
    }

    // Best-effort protection: prevent direct web access to license documents.
    $htaccess = $uploadDirAbs . '/.htaccess';
    if (!file_exists($htaccess)) {
      @file_put_contents(
        $htaccess,
        "# Protect license documents\n" .
          "<IfModule mod_authz_core.c>\n" .
          "Require all denied\n" .
          "</IfModule>\n" .
          "<IfModule !mod_authz_core.c>\n" .
          "Deny from all\n" .
          "</IfModule>\n"
      );
    }
    $indexHtml = $uploadDirAbs . '/index.html';
    if (!file_exists($indexHtml)) {
      @file_put_contents($indexHtml, '');
    }

    try {
      $rand = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
      $rand = (string)mt_rand(100000, 999999);
    }

    $filename = 'license_doctor_' . $doctorId . '_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
    $destAbs = $uploadDirAbs . '/' . $filename;
    $relPath = 'uploads/licenses/' . $filename;

    if (!move_uploaded_file($tmp, $destAbs)) {
      return ['success' => false, 'message' => 'Failed to save license document.'];
    }

    // Remove old license file (if any)
    $old = '';
    $stmtOld = $conn->prepare('SELECT license_document FROM users WHERE id = ? LIMIT 1');
    if ($stmtOld) {
      $stmtOld->bind_param('i', $doctorId);
      $stmtOld->execute();
      $row = $stmtOld->get_result()->fetch_assoc();
      $old = (string)($row['license_document'] ?? '');
      $stmtOld->close();
    }

    $stmtUp = $conn->prepare('UPDATE users SET license_document = ? WHERE id = ?');
    if ($stmtUp) {
      $stmtUp->bind_param('si', $relPath, $doctorId);
      $stmtUp->execute();
      $stmtUp->close();
    }

    if ($old !== '') {
      $old = str_replace(['\\', "\0"], ['/', ''], $old);
      $old = ltrim($old, '/');
      $oldAbs = rtrim(str_replace('\\', '/', __DIR__), '/') . '/' . $old;
      $base = realpath($uploadDirAbs);
      $real = realpath($oldAbs);
      if ($base !== false && $real !== false) {
        $base = str_replace('\\', '/', $base) . '/';
        $real = str_replace('\\', '/', $real);
        if (substr($real, 0, strlen($base)) === $base && file_exists($real)) {
          @unlink($real);
        }
      }
    }

    return ['success' => true, 'message' => 'License uploaded.', 'path' => $relPath];
  }
}

if (!function_exists('medikit_doctor_is_verified')) {
  function medikit_doctor_is_verified(mysqli $conn, int $doctorId): bool
  {
    medikit_doctor_verification_ensure_schema($conn);

    if ($doctorId <= 0) {
      return false;
    }

    $stmt = $conn->prepare("SELECT verification_status FROM users WHERE id = ? AND role_id = 2 LIMIT 1");
    if (!$stmt) {
      return false;
    }
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return isset($row['verification_status']) && $row['verification_status'] === 'verified';
  }
}

if (!function_exists('medikit_commission_upsert_for_booking')) {
  function medikit_commission_upsert_for_booking(mysqli $conn, int $bookingId): void
  {
    if ($bookingId <= 0) {
      return;
    }

    medikit_doctor_verification_ensure_schema($conn);
    medikit_commission_ensure_schema($conn);

    $q = "
        SELECT
          vb.id AS booking_id,
          vb.doctor_id,
          vb.patient_id,
          cb.id AS bill_id,
          cb.amount,
          cb.payment_status
        FROM visit_booking vb
        INNER JOIN clinic_bills cb ON cb.booking_id = vb.id
        INNER JOIN users u ON u.id = vb.doctor_id
        WHERE vb.id = ? AND vb.status = 'visited'
        LIMIT 1
      ";

    $stmt = $conn->prepare($q);
    if (!$stmt) {
      return;
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
      return;
    }

    $payment_status = (string)($row['payment_status'] ?? 'pending');
    if ($payment_status !== 'paid') {
      // Commission becomes due only after the related bill is paid.
      // If a due commission exists for this booking, remove it.
      $del = $conn->prepare("DELETE FROM doctor_commissions WHERE booking_id = ? AND status = 'due'");
      if ($del) {
        $del->bind_param('i', $bookingId);
        $del->execute();
        $del->close();
      }
      return;
    }

    $amount = (float)($row['amount'] ?? 0);
    $percent = medikit_fixed_commission_percent();
    if ($amount <= 0 || $percent < 0) {
      return;
    }

    $commission = round(($amount * $percent) / 100, 2);

    $ins = "
            INSERT INTO doctor_commissions
                (booking_id, bill_id, doctor_id, patient_id, amount, commission_percent, commission_amount, status)
            VALUES
                (?,?,?,?,?,?,?, 'due')
            ON DUPLICATE KEY UPDATE
                bill_id = VALUES(bill_id),
                doctor_id = VALUES(doctor_id),
                patient_id = VALUES(patient_id),
                amount = IF(status = 'due', VALUES(amount), amount),
                commission_percent = IF(status = 'due', VALUES(commission_percent), commission_percent),
                commission_amount = IF(status = 'due', VALUES(commission_amount), commission_amount)
        ";

    $stmt2 = $conn->prepare($ins);
    if (!$stmt2) {
      return;
    }

    $booking_id = (int)$row['booking_id'];
    $bill_id = (int)$row['bill_id'];
    $doctor_id = (int)$row['doctor_id'];
    $patient_id = (int)$row['patient_id'];

    $stmt2->bind_param('iiiiddd', $booking_id, $bill_id, $doctor_id, $patient_id, $amount, $percent, $commission);
    $stmt2->execute();
    $stmt2->close();
  }
}
