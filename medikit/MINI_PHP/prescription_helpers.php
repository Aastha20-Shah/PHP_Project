<?php

declare(strict_types=1);

function prescriptions_ensure_schema(mysqli $conn): void
{
  $sql = "CREATE TABLE IF NOT EXISTS `clinic_prescriptions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `booking_id` INT(11) NOT NULL,
        `doctor_id` INT(11) NOT NULL,
        `patient_id` INT(11) NOT NULL,
        `diagnosis` VARCHAR(255) NOT NULL DEFAULT '',
        `medications` TEXT NOT NULL,
        `dosage` VARCHAR(100) NOT NULL DEFAULT '',
        `frequency` VARCHAR(100) NOT NULL DEFAULT '',
        `duration` VARCHAR(100) NOT NULL DEFAULT '',
        `instructions` TEXT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_booking` (`booking_id`),
        KEY `idx_doctor` (`doctor_id`),
        KEY `idx_patient` (`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

  $conn->query($sql);
}

function prescriptions_code(int $prescriptionId): string
{
  return 'RX-' . str_pad((string)$prescriptionId, 4, '0', STR_PAD_LEFT);
}

function prescriptions_normalize_status(string $status): string
{
  $status = strtolower(trim($status));
  return $status === 'inactive' ? 'inactive' : 'active';
}

/**
 * Decodes structured medication items stored as JSON in the `medications` column.
 *
 * Supported formats:
 * - {"items":[{"medicine":"...","dosage":"...","frequency":"...","duration":"...","time":"..."}, ...]}
 * - [{"medicine":"...", ...}, ...]
 *
 * Returns null when medications is plain text or JSON is not in a supported shape.
 *
 * @return array<int, array{medicine:string,dosage:string,frequency:string,duration:string,time:string}>|null
 */
function prescriptions_decode_medication_items(string $medications): ?array
{
  $raw = trim($medications);
  if ($raw === '') {
    return null;
  }

  $first = $raw[0] ?? '';
  if ($first !== '{' && $first !== '[') {
    return null;
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
    return null;
  }

  $items = null;
  if (isset($decoded['items']) && is_array($decoded['items'])) {
    $items = $decoded['items'];
  } elseif (isset($decoded[0]) && is_array($decoded[0])) {
    $items = $decoded;
  }

  if (!is_array($items)) {
    return null;
  }

  $out = [];
  foreach ($items as $item) {
    if (!is_array($item)) {
      continue;
    }

    $medicine = trim((string)($item['medicine'] ?? $item['name'] ?? ''));
    $dosage = trim((string)($item['dosage'] ?? ''));
    $frequency = trim((string)($item['frequency'] ?? ''));
    $duration = trim((string)($item['duration'] ?? ''));
    $time = trim((string)($item['time'] ?? $item['timing'] ?? ''));

    if ($medicine === '' && $dosage === '' && $frequency === '' && $duration === '' && $time === '') {
      continue;
    }

    $out[] = [
      'medicine' => $medicine,
      'dosage' => $dosage,
      'frequency' => $frequency,
      'duration' => $duration,
      'time' => $time,
    ];
  }

  return empty($out) ? null : $out;
}
