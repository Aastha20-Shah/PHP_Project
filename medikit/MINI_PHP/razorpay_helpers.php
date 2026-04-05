<?php

declare(strict_types=1);

require_once __DIR__ . '/razorpay_config.php';

function medikit_razorpay_config(): array
{
  $keyId = (string)(defined('MEDIKIT_RAZORPAY_KEY_ID') ? MEDIKIT_RAZORPAY_KEY_ID : '');
  $keySecret = (string)(defined('MEDIKIT_RAZORPAY_KEY_SECRET') ? MEDIKIT_RAZORPAY_KEY_SECRET : '');
  $currency = (string)(defined('MEDIKIT_RAZORPAY_CURRENCY') ? MEDIKIT_RAZORPAY_CURRENCY : 'INR');

  if ($keyId === '' || $keySecret === '') {
    throw new RuntimeException('Razorpay keys are not configured.');
  }

  return [
    'key_id' => $keyId,
    'key_secret' => $keySecret,
    'currency' => $currency,
    'api_base' => 'https://api.razorpay.com/v1',
  ];
}

function medikit_money_to_paise(string $amount): int
{
  $amount = trim($amount);
  if ($amount === '') {
    return 0;
  }

  if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
    return 0;
  }

  $parts = explode('.', $amount, 2);
  $rupeesStr = $parts[0];
  $paiseStr = $parts[1] ?? '0';
  $paiseStr = substr($paiseStr, 0, 2);
  $paiseStr = str_pad($paiseStr, 2, '0');

  $rupees = (int)$rupeesStr;
  $paise = (int)$paiseStr;

  return ($rupees * 100) + $paise;
}

function medikit_razorpay_method_to_bill_payment_method(?string $method): string
{
  $m = strtolower(trim((string)$method));
  if ($m === 'upi') {
    return 'UPI';
  }
  if ($m === 'netbanking') {
    return 'Net Banking';
  }
  if ($m === 'card') {
    return 'Credit Card';
  }

  return 'Other';
}

function medikit_razorpay_verify_signature(string $orderId, string $paymentId, string $signature): bool
{
  $cfg = medikit_razorpay_config();
  $data = $orderId . '|' . $paymentId;
  $expected = hash_hmac('sha256', $data, $cfg['key_secret']);
  return hash_equals($expected, $signature);
}

function medikit_razorpay_api_request(string $method, string $path, ?array $payload = null): array
{
  $cfg = medikit_razorpay_config();

  if (!function_exists('curl_init')) {
    return [
      'ok' => false,
      'status' => 0,
      'data' => null,
      'error' => 'PHP cURL extension is not enabled.',
    ];
  }

  $url = rtrim($cfg['api_base'], '/') . '/' . ltrim($path, '/');

  $ch = curl_init($url);
  if (!$ch) {
    return [
      'ok' => false,
      'status' => 0,
      'data' => null,
      'error' => 'Failed to initialize cURL.',
    ];
  }

  $headers = ['Content-Type: application/json'];

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, $cfg['key_id'] . ':' . $cfg['key_secret']);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);

  if ($payload !== null) {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      curl_close($ch);
      return [
        'ok' => false,
        'status' => 0,
        'data' => null,
        'error' => 'Failed to encode JSON payload.',
      ];
    }

    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  }

  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $body = curl_exec($ch);
  $curlErr = curl_error($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($body === false) {
    return [
      'ok' => false,
      'status' => $status,
      'data' => null,
      'error' => $curlErr !== '' ? $curlErr : 'Request failed.',
    ];
  }

  $decoded = json_decode((string)$body, true);
  if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    $decoded = null;
  }

  if ($status >= 200 && $status < 300) {
    return [
      'ok' => true,
      'status' => $status,
      'data' => $decoded,
      'error' => '',
    ];
  }

  $message = 'Razorpay API error.';
  if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
    $desc = (string)($decoded['error']['description'] ?? '');
    $code = (string)($decoded['error']['code'] ?? '');
    if ($code !== '' && $desc !== '') {
      $message = $code . ': ' . $desc;
    } elseif ($desc !== '') {
      $message = $desc;
    }
  }

  return [
    'ok' => false,
    'status' => $status,
    'data' => $decoded,
    'error' => $message,
  ];
}

function medikit_razorpay_create_order(int $amountPaise, string $currency, string $receipt, array $notes = []): array
{
  $payload = [
    'amount' => $amountPaise,
    'currency' => $currency,
    'receipt' => $receipt,
    'notes' => (object)$notes,
  ];

  return medikit_razorpay_api_request('POST', '/orders', $payload);
}

function medikit_razorpay_fetch_payment(string $paymentId): array
{
  return medikit_razorpay_api_request('GET', '/payments/' . rawurlencode($paymentId), null);
}
