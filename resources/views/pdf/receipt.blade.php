{{-- resources/views/pdf/receipt.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        .header { background: #1a2332; color: white; padding: 16px; text-align: center; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header .subtitle { font-size: 9px; opacity: 0.7; margin-top: 2px; }
        .receipt-title { background: #2563eb; color: white; text-align: center; padding: 8px; font-size: 12px; font-weight: bold; letter-spacing: 2px; }
        .body { padding: 16px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 6px; border-bottom: 1px dotted #e2e8f0; padding-bottom: 4px; }
        .label { color: #64748b; font-size: 9px; text-transform: uppercase; }
        .value { font-weight: bold; font-size: 10px; }
        .amount-box { background: #f0fdf4; border: 2px solid #16a34a; border-radius: 8px; padding: 12px; text-align: center; margin: 12px 0; }
        .amount-box .big { font-size: 22px; font-weight: 900; color: #16a34a; }
        .amount-box .label2 { font-size: 9px; color: #64748b; }
        .breakdown { background: #f8fafc; border-radius: 4px; padding: 10px; margin: 8px 0; }
        .breakdown .item { display: flex; justify-content: space-between; font-size: 9px; margin-bottom: 3px; }
        .footer { text-align: center; padding: 10px; background: #f8fafc; font-size: 8px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .qr-placeholder { width: 50px; height: 50px; border: 2px solid #e2e8f0; margin: 0 auto 6px; display: flex; align-items: center; justify-content: center; font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $company['name'] }}</h1>
    <div class="subtitle">{{ $company['address'] }} &nbsp;|&nbsp; {{ $company['phone'] }}</div>
</div>
<div class="receipt-title">PAYMENT RECEIPT</div>

<div class="body">
    <div class="row"><span class="label">Receipt No.</span><span class="value" style="color:#2563eb">{{ $repayment->receipt_number }}</span></div>
    <div class="row"><span class="label">Date & Time</span><span class="value">{{ $repayment->payment_date->format('d/m/Y') }} {{ $repayment->payment_time }}</span></div>
    <div class="row"><span class="label">Borrower</span><span class="value">{{ $borrower->full_name }}</span></div>
    <div class="row"><span class="label">Phone</span><span class="value">{{ $borrower->primary_phone }}</span></div>
    <div class="row"><span class="label">Loan Number</span><span class="value">{{ $loan->loan_number }}</span></div>
    <div class="row"><span class="label">Branch</span><span class="value">{{ $branch->name }}</span></div>
    <div class="row"><span class="label">Payment Method</span><span class="value">{{ strtoupper(str_replace('_',' ',$repayment->payment_method)) }}</span></div>
    @if($repayment->payment_reference)
    <div class="row"><span class="label">Reference</span><span class="value">{{ $repayment->payment_reference }}</span></div>
    @endif
    @if($repayment->paystack_reference)
    <div class="row"><span class="label">Paystack Ref</span><span class="value">{{ $repayment->paystack_reference }}</span></div>
    @endif

    <div class="amount-box">
        <div class="label2">AMOUNT PAID</div>
        <div class="big">₵{{ number_format($repayment->amount, 2) }}</div>
    </div>

    <div class="breakdown">
        <div style="font-size:9px;font-weight:bold;margin-bottom:5px;color:#374151">PAYMENT BREAKDOWN</div>
        <div class="item"><span>Principal</span><span>₵{{ number_format($repayment->principal_paid, 2) }}</span></div>
        <div class="item"><span>Interest</span><span>₵{{ number_format($repayment->interest_paid, 2) }}</span></div>
        <div class="item"><span>Fees</span><span>₵{{ number_format($repayment->fees_paid, 2) }}</span></div>
        <div class="item"><span>Penalties</span><span>₵{{ number_format($repayment->penalty_paid, 2) }}</span></div>
        <div class="item" style="border-top:1px solid #d1d5db;padding-top:3px;font-weight:bold"><span>Remaining Balance</span><span>₵{{ number_format($loan->total_outstanding, 2) }}</span></div>
    </div>

    @if($loan->next_due_date)
    <div class="row" style="margin-top:6px"><span class="label">Next Payment Due</span><span class="value" style="color:#d97706">{{ $loan->next_due_date }}</span></div>
    @if($loan->next_due_amount > 0)
    <div class="row"><span class="label">Next Amount Due</span><span class="value">₵{{ number_format($loan->next_due_amount, 2) }}</span></div>
    @endif
    @endif
</div>

<div class="footer">
    {{ $company['footer'] ?? 'Thank you for your payment.' }}<br>
    This is a computer-generated receipt — no signature required.<br>
    {{ $company['email'] }} | Generated: {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
