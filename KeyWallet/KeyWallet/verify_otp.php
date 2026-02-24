<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify OTP | KeyVault</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #e4e7f1 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }

    .otp-container {
      width: 100%;
      max-width: 500px;
      background: white;
      border-radius: 20px;
      box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1);
      padding: 60px 40px;
      text-align: center;
    }

    .logo-icon {
      width: 70px;
      height: 70px;
      background: #5e35b1;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 32px;
      margin: 0 auto 20px auto;
      box-shadow: 0 5px 20px rgba(94, 53, 177, 0.3);
    }

    .otp-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #2d3748;
    }

    .otp-subtitle {
      font-size: 16px;
      color: #718096;
      margin-bottom: 40px;
    }

    .otp-input {
      width: 100%;
      padding: 18px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 18px;
      text-align: center;
      letter-spacing: 8px;
      font-weight: 600;
      margin-bottom: 30px;
      transition: all 0.3s ease;
      margin-left:-20px;
    }

    .otp-input:focus {
      outline: none;
      border-color: #5e35b1;
      box-shadow: 0 0 0 4px rgba(94, 53, 177, 0.1);
    }

    .verify-btn {
      width: 100%;
      padding: 18px;
      background: linear-gradient(135deg, #5e35b1 0%, #3949ab 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-left:-5px;
    }

    .verify-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(94, 53, 177, 0.3);
    }

    .resend-link {
      margin-top: 20px;
      font-size: 14px;
      color: #5e35b1;
      cursor: pointer;
      font-weight: 500;
      display: inline-block;
    }

    .resend-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="otp-container">
    <div class="logo-icon">
      <i class="fas fa-key"></i>
    </div>
    <h1 class="otp-title">Verify OTP</h1>
    <p class="otp-subtitle">Enter the 6-digit code we sent to your email</p>

    <form action="check_otp.php" method="POST">
      <input type="text" name="otp" maxlength="6" class="otp-input" placeholder="______" required>
      <button type="submit" class="verify-btn">
        <i class="fas fa-check-circle"></i> Verify
      </button>
    </form>

    <a href="resend_otp.php" class="resend-link">Resend OTP</a>
  </div>
</body>
</html>
