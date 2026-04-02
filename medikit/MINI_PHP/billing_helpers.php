<?php

declare(strict_types=1);

function billing_ensure_schema(mysqli $conn): void
{
  $sql = "CREATE TABLE IF NOT EXISTS `clinic_bills` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `booking_id` INT(11) NOT NULL,
        `doctor_id` INT(11) NOT NULL,
        `patient_id` INT(11) NOT NULL,
        `service_type` VARCHAR(100) NOT NULL DEFAULT 'Consultation',
        `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash',
        `payment_status` ENUM('pending','paid') NOT NULL DEFAULT 'pending',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_booking` (`booking_id`),
        KEY `idx_doctor` (`doctor_id`),
        KEY `idx_patient` (`patient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

  $conn->query($sql);
}

function billing_invoice_no(int $billId): string
{
  return 'INV-' . str_pad((string)$billId, 4, '0', STR_PAD_LEFT);
}

function billing_normalize_payment_status(string $status): string
{
  $status = strtolower(trim($status));
  return $status === 'paid' ? 'paid' : 'pending';
}

function billing_normalize_payment_method(string $method): string
{
  $method = trim($method);
  $allowed = [
    'Cash',
    'Credit Card',
    'Debit Card',
    'UPI',
    'Net Banking',
    'Insurance',
    'Other',
  ];

  foreach ($allowed as $item) {
    if (strcasecmp($method, $item) === 0) {
      return $item;
    }
  }

  return 'Cash';
}
