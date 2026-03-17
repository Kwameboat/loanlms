<?php

namespace App\Exports;

use App\Models\Repayment;
use App\Models\Loan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ─── Repayments Export ────────────────────────────────────────────────────────

class RepaymentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected string $dateFrom,
        protected string $dateTo,
        protected ?int   $branchId = null
    ) {}

    public function title(): string { return 'Collections'; }

    public function query()
    {
        return Repayment::with(['loan', 'borrower', 'collectedBy', 'branch'])
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->orderBy('payment_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'Receipt #', 'Date', 'Borrower', 'Phone', 'Loan #',
            'Amount (GHS)', 'Principal', 'Interest', 'Fees', 'Penalty',
            'Method', 'Reference', 'Collector', 'Branch', 'Status',
        ];
    }

    public function map($r): array
    {
        return [
            $r->receipt_number,
            $r->payment_date->format('d/m/Y'),
            $r->borrower->full_name,
            $r->borrower->primary_phone,
            $r->loan->loan_number,
            number_format($r->amount, 2),
            number_format($r->principal_paid, 2),
            number_format($r->interest_paid, 2),
            number_format($r->fees_paid, 2),
            number_format($r->penalty_paid, 2),
            strtoupper(str_replace('_', ' ', $r->payment_method)),
            $r->payment_reference ?? $r->paystack_reference ?? '',
            $r->collectedBy->name ?? '',
            $r->branch->name ?? '',
            ucfirst($r->status),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                  'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A2332']]],
        ];
    }
}

// ─── Loans Export ─────────────────────────────────────────────────────────────

class LoansExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected ?string $dateFrom = null,
        protected ?string $dateTo   = null,
        protected ?int    $branchId = null,
        protected string  $type     = 'all'
    ) {}

    public function title(): string { return ucfirst($this->type) . ' Loans'; }

    public function query()
    {
        $q = Loan::with(['borrower', 'loanProduct', 'loanOfficer', 'branch']);

        if ($this->type === 'disbursements' && $this->dateFrom && $this->dateTo) {
            $q->whereBetween('disbursement_date', [$this->dateFrom, $this->dateTo]);
        }
        if ($this->branchId) $q->where('branch_id', $this->branchId);
        return $q->latest();
    }

    public function headings(): array
    {
        return [
            'Loan #', 'Borrower', 'Phone', 'Ghana Card', 'Product',
            'Principal', 'Approved', 'Disbursed', 'Outstanding',
            'Interest Rate', 'Term', 'Frequency',
            'Application Date', 'Disbursement Date', 'Maturity Date',
            'Status', 'Days Past Due', 'Officer', 'Branch',
        ];
    }

    public function map($l): array
    {
        return [
            $l->loan_number,
            $l->borrower->full_name,
            $l->borrower->primary_phone,
            $l->borrower->ghana_card_number ?? '',
            $l->loanProduct->name,
            number_format($l->requested_amount, 2),
            number_format($l->approved_amount ?? 0, 2),
            number_format($l->disbursed_amount ?? 0, 2),
            number_format($l->total_outstanding, 2),
            $l->interest_rate . '%',
            $l->term_months . ' months',
            ucfirst($l->repayment_frequency),
            $l->application_date ? $l->application_date->format('d/m/Y') : '',
            $l->disbursement_date ? $l->disbursement_date->format('d/m/Y') : '',
            $l->maturity_date ? $l->maturity_date->format('d/m/Y') : '',
            config('bigcash.loan.statuses.' . $l->status, ucfirst($l->status)),
            $l->days_past_due,
            $l->loanOfficer->name ?? '',
            $l->branch->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                  'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A2332']]],
        ];
    }
}

// ─── Arrears Export ───────────────────────────────────────────────────────────

class ArrearsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(protected ?int $branchId = null) {}

    public function title(): string { return 'Arrears Report'; }

    public function query()
    {
        return Loan::with(['borrower', 'loanProduct', 'loanOfficer', 'branch'])
            ->where('is_overdue', true)
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->orderBy('days_past_due', 'desc');
    }

    public function headings(): array
    {
        return [
            'Loan #', 'Borrower', 'Phone', 'Product', 'Disbursed Amount',
            'Outstanding Principal', 'Total Outstanding', 'Days Past Due',
            'Overdue Since', 'Officer', 'Branch',
        ];
    }

    public function map($l): array
    {
        return [
            $l->loan_number,
            $l->borrower->full_name,
            $l->borrower->primary_phone,
            $l->loanProduct->name,
            number_format($l->disbursed_amount ?? 0, 2),
            number_format($l->outstanding_principal, 2),
            number_format($l->total_outstanding, 2),
            $l->days_past_due,
            $l->overdue_since ? $l->overdue_since->format('d/m/Y') : '',
            $l->loanOfficer->name ?? '',
            $l->branch->name ?? '',
        ];
    }
}

// ─── Collector Performance Export ────────────────────────────────────────────

class CollectorPerformanceExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected string $dateFrom,
        protected string $dateTo,
        protected ?int   $branchId = null
    ) {}

    public function title(): string { return 'Collector Performance'; }

    public function query()
    {
        return Repayment::select(
                'collected_by',
                \DB::raw('COUNT(*) as count'),
                \DB::raw('SUM(amount) as total'),
                \DB::raw('SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END) as cash_total'),
                \DB::raw('SUM(CASE WHEN payment_method = "mobile_money" THEN amount ELSE 0 END) as momo_total')
            )
            ->with('collectedBy')
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])
            ->when($this->branchId, fn($q) => $q->where('branch_id', $this->branchId))
            ->groupBy('collected_by');
    }

    public function headings(): array
    {
        return ['Collector', 'Count', 'Total (GHS)', 'Cash (GHS)', 'MoMo (GHS)'];
    }

    public function map($row): array
    {
        return [
            $row->collectedBy->name ?? 'Unknown',
            $row->count,
            number_format($row->total, 2),
            number_format($row->cash_total, 2),
            number_format($row->momo_total, 2),
        ];
    }
}
