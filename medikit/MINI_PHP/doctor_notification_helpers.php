<?php

if (!function_exists('medikit_ensure_doctor_notification_schema')) {
  function medikit_ensure_doctor_notification_schema($conn): void
  {
    if (!($conn instanceof mysqli)) {
      return;
    }

    $res = $conn->query("SHOW COLUMNS FROM `visit_booking` LIKE 'doctor_seen'");
    if ($res && $res->num_rows === 0) {
      $conn->query("ALTER TABLE `visit_booking` ADD COLUMN `doctor_seen` TINYINT(1) NOT NULL DEFAULT 0");
      // Mark existing bookings as seen to avoid flooding notifications.
      $conn->query("UPDATE `visit_booking` SET `doctor_seen` = 1");
    }
  }
}

if (!function_exists('medikit_doctor_unseen_notifications_count')) {
  function medikit_doctor_unseen_notifications_count($conn, int $doctorId): int
  {
    medikit_ensure_doctor_notification_schema($conn);

    if (!($conn instanceof mysqli) || $doctorId <= 0) {
      return 0;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM visit_booking WHERE doctor_id = ? AND doctor_seen = 0 AND status IN ('pending','accepted')");
    if (!$stmt) {
      return 0;
    }

    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0);
  }
}

if (!function_exists('medikit_doctor_unseen_notifications_list')) {
  function medikit_doctor_unseen_notifications_list($conn, int $doctorId, int $limit = 5): array
  {
    medikit_ensure_doctor_notification_schema($conn);

    if (!($conn instanceof mysqli) || $doctorId <= 0) {
      return [];
    }

    $limit = max(1, min(20, $limit));

    $q = "
      SELECT
        vb.id AS booking_id,
        vb.appointment_date,
        vb.status,
        p.firstname AS patient_firstname,
        p.lastname AS patient_lastname,
        dat.start_time
      FROM visit_booking vb
      LEFT JOIN patient p ON p.id = vb.patient_id
      LEFT JOIN doctor_available_time dat ON dat.id = vb.time_id
      WHERE vb.doctor_id = ?
        AND vb.doctor_seen = 0
        AND vb.status IN ('pending','accepted')
      ORDER BY vb.id DESC
      LIMIT ?
    ";

    $stmt = $conn->prepare($q);
    if (!$stmt) {
      return [];
    }

    $stmt->bind_param('ii', $doctorId, $limit);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
      }
    }

    $stmt->close();
    return $rows;
  }
}

if (!function_exists('medikit_doctor_clear_notifications')) {
  function medikit_doctor_clear_notifications($conn, int $doctorId): void
  {
    medikit_ensure_doctor_notification_schema($conn);

    if (!($conn instanceof mysqli) || $doctorId <= 0) {
      return;
    }

    $stmt = $conn->prepare("UPDATE visit_booking SET doctor_seen = 1 WHERE doctor_id = ? AND doctor_seen = 0");
    if ($stmt) {
      $stmt->bind_param('i', $doctorId);
      $stmt->execute();
      $stmt->close();
    }
  }
}
