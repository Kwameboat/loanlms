{{-- resources/views/emails/password-reset.blade.php --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif}
  .wrapper{max-width:500px;margin:0 auto;padding:20px 16px}
  .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08)}
  .header{background:#1a2332;padding:24px;text-align:center;color:#fff}
  .body{padding:28px 24px}
  .body p{color:#374151;font-size:14px;line-height:1.6}
  .btn{display:inline-block;background:#2563eb;color:#fff !important;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:15px;font-weight:600;margin:16px 0}
  .footer{padding:12px;background:#f8fafc;text-align:center;font-size:11px;color:#94a3b8}
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header"><h2 style="margin:0;font-size:18px">{{ $company }}</h2><p style="margin:4px 0 0;opacity:0.7;font-size:12px">Password Reset Request</p></div>
    <div class="body">
      <p>Hello <strong>{{ $user->name }}</strong>,</p>
      <p>You requested a password reset for your account. Click the button below to set a new password:</p>
      <div style="text-align:center"><a href="{{ $resetUrl }}" class="btn">Reset My Password</a></div>
      <p>This link expires in <strong>1 hour</strong>. If you did not request a reset, please ignore this email.</p>
      <p style="font-size:12px;color:#94a3b8;word-break:break-all">Or copy this link: {{ $resetUrl }}</p>
    </div>
    <div class="footer">© {{ date('Y') }} {{ $company }}</div>
  </div>
</div>
</body></html>
