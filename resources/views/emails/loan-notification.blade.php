{{-- resources/views/emails/loan-notification.blade.php --}}
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,sans-serif}
  .wrapper{max-width:600px;margin:0 auto;padding:20px 16px}
  .card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.07)}
  .header{background:#1a2332;padding:24px;text-align:center}
  .header h1{color:#fff;font-size:18px;margin:0}
  .header p{color:rgba(255,255,255,0.6);font-size:12px;margin:4px 0 0}
  .body{padding:28px 24px}
  .body p{color:#374151;font-size:14px;line-height:1.6;margin:0 0 12px}
  .highlight{background:#f0f9ff;border-left:4px solid #2563eb;padding:12px 16px;border-radius:0 8px 8px 0;margin:16px 0}
  .highlight strong{color:#1e40af;font-size:15px}
  .footer{padding:16px 24px;background:#f8fafc;text-align:center;font-size:11px;color:#94a3b8}
  .btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;margin:12px 0}
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <h1>{{ $company['name'] }}</h1>
      <p>Loan Management System</p>
    </div>
    <div class="body">
      <p>Dear <strong>{{ $borrower->display_name }}</strong>,</p>
      @foreach($vars as $key => $value)
        @if($key === '{amount}' || $key === '{balance}' || $key === '{installment}')
          {{-- rendered in message body --}}
        @endif
      @endforeach

      @if($loan)
      <div class="highlight">
        <div style="font-size:12px;color:#64748b;margin-bottom:4px">LOAN DETAILS</div>
        <strong>{{ $loan->loan_number }}</strong> — {{ $loan->loanProduct->name ?? '' }}<br>
        <span style="font-size:12px;color:#64748b">Outstanding: {{ config('bigcash.company.currency_symbol','₵') }}{{ number_format($loan->total_outstanding, 2) }}</span>
      </div>
      @endif

      <p>If you have any questions, please contact us:</p>
      <p style="font-size:13px;color:#64748b">📞 {{ $company['phone'] }} &nbsp;|&nbsp; ✉ {{ $company['email'] }}</p>
    </div>
    <div class="footer">
      © {{ date('Y') }} {{ $company['name'] }} &nbsp;|&nbsp; This is an automated message.
    </div>
  </div>
</div>
</body></html>
