{{-- resources/views/emails/otp.blade.php --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif}
  .wrapper{max-width:480px;margin:0 auto;padding:20px 16px}
  .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)}
  .header{background:linear-gradient(135deg,#1a2332,#2563eb);padding:28px;text-align:center;color:#fff}
  .otp-box{font-size:40px;font-weight:900;letter-spacing:12px;color:#2563eb;text-align:center;padding:20px;background:#eff6ff;border-radius:8px;margin:20px 0}
  .body{padding:24px}
  .footer{padding:12px;background:#f8fafc;text-align:center;font-size:11px;color:#94a3b8}
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <h2 style="margin:0;font-size:20px">{{ $company }}</h2>
      <p style="margin:4px 0 0;opacity:0.7;font-size:13px">Login Verification Code</p>
    </div>
    <div class="body">
      <p style="color:#374151;font-size:14px">Hello <strong>{{ $user->name }}</strong>,</p>
      <p style="color:#374151;font-size:14px">Your one-time verification code is:</p>
      <div class="otp-box">{{ $otp }}</div>
      <p style="color:#64748b;font-size:13px">This code expires in <strong>10 minutes</strong>. Do not share this code with anyone.</p>
      <p style="color:#64748b;font-size:12px">If you did not request this, please ignore this email and ensure your account is secure.</p>
    </div>
    <div class="footer">© {{ date('Y') }} {{ $company }}</div>
  </div>
</div>
</body></html>
