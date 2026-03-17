<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { background: #1a2332; color: white; padding: 14px; }
        .header h1 { font-size: 14px; font-weight: bold; }
        .header p { font-size: 8px; opacity: 0.7; }
        .title-bar { background: #2563eb; color: white; padding: 8px 14px; font-size: 11px; font-weight: bold; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px 14px; background: #f8fafc; }
        .info-item .lbl { font-size: 8px; color: #64748b; text-transform: uppercase; }
        .info-item .val { font-weight: bold; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 0; }
        thead th { background: #1e293b; color: white; padding: 6px 8px; font-size: 8px; text-align: right; }
        thead th:first-child { text-align: center; }
        tbody td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 8.5px; text-align: right; }
        tbody td:first-child { text-align: center; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr.overdue { background: #fff1f2; }
        tbody tr.paid { background: #f0fdf4; }
        tfoot td { background: #1e293b; color: white; padding: 6px 8px; font-weight: bold; font-size: 8.5px; text-align: right; }
        .footer { padding: 10px 14px; text-align: center; font-size: 8px; color: #64748b; border-top: 2px solid #e2e8f0; margin-top: 10px; }
        .status-badge { padding: 1px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; }
        .status-pending  { background: #dbeafe; color: #1d4ed8; }
        .status-paid     { background: #dcfce7; color: #15803d; }
        .status-overdue  { background: #fee2e2; color: #dc2626; }
        .status-partial  { background: #fef9c3; color: #92400e; }
    </style>
</head>
<body>
<div class="header">
    <h1>{{ $company['name'] }}</h1>
    <p>{{ $company['address'] ?? '' }} | {{ $company['phone'] ?? '' }}</p>
</div>
<div class="title-bar">LOAN REPAYMENT SCHEDULE</div>

<div class="info-grid">
    <div class="info-item"><div class="lbl">Borrower Name</div><div class="val">{{ $borrower->full_name }}</div></div>
    <div class="info-item"><div class="lbl">Loan Number</div><div class="val">{{ $loan->loan_number }}</div></div>
    <div class="info-item"><div class="lbl">Loan Product</div><div class="val">{{ $loan->loanProduct->name }}</div></div>
    <div class="info-item"><div class="lbl">Principal Amount</div><div class="val">₵{{ number_format($loan->disbursed_amount, 2) }}</div></div>
    <div class="info-item"><div class="lbl">Interest Rate</div><div class="val">{{ $loan->interest_rate }}% per annum ({{ $loan->interest_type }})</div></div>
    <div class="info-item"><div class="lbl">Term</div><div class="val">{{ $loan->term_months }} months ({{ str_replace('_',' ',ucfirst($loan->repayment_frequency)) }})</div></div>
    <div class="info-item"><div class="lbl">Disbursement Date</div><div class="val">{{ $loan->disbursement_date ? $loan->disbursement_date->format('d/m/Y') : 'N/A' }}</div></div>
    <div class="info-item"><div class="lbl">Maturity Date</div><div class="val">{{ $loan->maturity_date ? $loan->maturity_date->format('d/m/Y') : 'N/A' }}</div></div>
    <div class="info-item"><div class="lbl">Total Repayable</div><div class="val">₵{{ number_format($loan->total_repayable, 2) }}</div></div>
    <div class="info-item"><div class="lbl">Installment Amount</div><div class="val">₵{{ number_format($loan->installment_amount, 2) }}</div></div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Due Date</th>
            <th>Opening Balance</th>
            <th>Principal</th>
            <th>Interest</th>
            <th>Fees</th>
            <th>Penalty</th>
            <th>Total Due</th>
            <th>Total Paid</th>
            <th>Closing Balance</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($schedule as $s)
        <tr class="status-{{ $s->status }}">
            <td>{{ $s->installment_number }}</td>
            <td>{{ $s->due_date->format('d/m/Y') }}</td>
            <td>₵{{ number_format($s->opening_balance, 2) }}</td>
            <td>₵{{ number_format($s->principal_due, 2) }}</td>
            <td>₵{{ number_format($s->interest_due, 2) }}</td>
            <td>₵{{ number_format($s->fees_due, 2) }}</td>
            <td>₵{{ number_format($s->penalty_due, 2) }}</td>
            <td>₵{{ number_format($s->total_due, 2) }}</td>
            <td>₵{{ number_format($s->total_paid, 2) }}</td>
            <td>₵{{ number_format($s->closing_balance, 2) }}</td>
            <td><span class="status-badge status-{{ $s->status }}">{{ ucfirst($s->status) }}</span></td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">TOTALS</td>
            <td>₵{{ number_format($schedule->sum('principal_due'), 2) }}</td>
            <td>₵{{ number_format($schedule->sum('interest_due'), 2) }}</td>
            <td>₵{{ number_format($schedule->sum('fees_due'), 2) }}</td>
            <td>₵{{ number_format($schedule->sum('penalty_due'), 2) }}</td>
            <td>₵{{ number_format($schedule->sum('total_due'), 2) }}</td>
            <td>₵{{ number_format($schedule->sum('total_paid'), 2) }}</td>
            <td colspan="2">Outstanding: ₵{{ number_format($loan->total_outstanding, 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    Generated by {{ $company['name'] }} | {{ now()->format('d/m/Y H:i') }}<br>
    This schedule is subject to change based on early payments, rescheduling, or penalties.<br>
    For enquiries call: {{ $company['phone'] ?? '' }}
</div>
</body>
</html>
