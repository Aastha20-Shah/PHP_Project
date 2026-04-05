<?php

declare(strict_types=1);

// Razorpay configuration.
//
// For production, do NOT hard-code secrets in a repo.
// Prefer environment variables (RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET).

if (!defined('MEDIKIT_RAZORPAY_KEY_ID')) {
  $env = getenv('RAZORPAY_KEY_ID');
  define('MEDIKIT_RAZORPAY_KEY_ID', is_string($env) && $env !== '' ? $env : 'rzp_test_RJYHP1TteC3Y5A');
}

if (!defined('MEDIKIT_RAZORPAY_KEY_SECRET')) {
  $env = getenv('RAZORPAY_KEY_SECRET');
  define('MEDIKIT_RAZORPAY_KEY_SECRET', is_string($env) && $env !== '' ? $env : 'oc0QrlClmESt0MayJMoPbhPK');
}

if (!defined('MEDIKIT_RAZORPAY_CURRENCY')) {
  define('MEDIKIT_RAZORPAY_CURRENCY', 'INR');
}
