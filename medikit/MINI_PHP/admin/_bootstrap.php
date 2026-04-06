<?php

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin_helpers.php';

// Ensure required tables/columns exist.
medikit_admin_ensure_schema($conn);
medikit_doctor_verification_ensure_schema($conn);
medikit_commission_ensure_schema($conn);
medikit_contact_messages_ensure_schema($conn);

if (!function_exists('admin_is_logged_in')) {
  function admin_is_logged_in(): bool
  {
    return isset($_SESSION['admin_id']);
  }
}

if (!function_exists('admin_require_login')) {
  function admin_require_login(): void
  {
    if (!admin_is_logged_in()) {
      header('Location: login.php');
      exit;
    }
  }
}

if (!function_exists('admin_name')) {
  function admin_name(): string
  {
    return (string)($_SESSION['admin_name'] ?? 'Admin');
  }
}

if (!function_exists('admin_h')) {
  function admin_h(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
