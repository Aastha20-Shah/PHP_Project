<?php

// SMTP mail configuration for Medikit.
// SECURITY: Keep real credentials out of source control.

if (!defined('MEDIKIT_MAIL_ENABLED')) {
  define('MEDIKIT_MAIL_ENABLED', true);
}

if (!defined('MEDIKIT_SMTP_HOST')) {
  define('MEDIKIT_SMTP_HOST', 'smtp.gmail.com');
}

if (!defined('MEDIKIT_SMTP_PORT')) {
  define('MEDIKIT_SMTP_PORT', 587);
}

if (!defined('MEDIKIT_SMTP_SECURE')) {
  // Use: 'tls' for port 587, 'ssl' for port 465
  define('MEDIKIT_SMTP_SECURE', 'tls');
}

if (!defined('MEDIKIT_SMTP_USERNAME')) {
  // e.g. your Gmail / SMTP username
  define('MEDIKIT_SMTP_USERNAME', 'ashah464@rku.ac.in');
}

if (!defined('MEDIKIT_SMTP_PASSWORD')) {
  // e.g. Gmail App Password (recommended) or SMTP password
  // Note: Gmail App Passwords are often shown with spaces; store it WITHOUT spaces.
  define('MEDIKIT_SMTP_PASSWORD', 'zkxklgeodbpivoov');
}

if (!defined('MEDIKIT_MAIL_FROM')) {
  // The "From" email address shown to patients
  define('MEDIKIT_MAIL_FROM', MEDIKIT_SMTP_USERNAME);
}

if (!defined('MEDIKIT_MAIL_FROM_NAME')) {
  define('MEDIKIT_MAIL_FROM_NAME', 'Medkit');
}

if (!defined('MEDIKIT_MAIL_DEBUG')) {
  // Set true to log SMTP debug messages to PHP error log
  define('MEDIKIT_MAIL_DEBUG', true);
}
