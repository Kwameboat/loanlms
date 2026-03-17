<?php

namespace App\Services\Loan;

use App\Models\Repayment;
use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    /**
     * Generate a PDF receipt for a repayment.
     * Saves to storage and updates repayment record.
     */
    public function generate(Repayment $repayment): string
    {
        $repayment->load(['loan.borrower', 'loan.loanProduct', 'collectedBy', 'branch']);

        $data = [
            'repayment' => $repayment,
            'loan'      => $repayment->loan,
            'borrower'  => $repayment->loan->borrower,
            'branch'    => $repayment->branch,
            'company'   => [
                'name'     => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
                'address'  => \App\Models\Setting::get('company_address', ''),
                'phone'    => \App\Models\Setting::get('company_phone', config('bigcash.company.phone')),
                'email'    => \App\Models\Setting::get('company_email', config('bigcash.company.email')),
                'logo'     => \App\Models\Setting::get('company_logo', ''),
                'footer'   => \App\Models\Setting::get('receipt_footer', 'Thank you for your payment.'),
            ],
        ];

        $pdf  = Pdf::loadView('pdf.receipt', $data);
        $pdf->setPaper('A5', 'portrait');

        $path = 'receipts/' . $repayment->receipt_number . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $repayment->update(['receipt_path' => $path]);

        return $path;
    }

    /**
     * Generate a downloadable repayment schedule PDF.
     */
    public function generateSchedulePdf(Loan $loan): string
    {
        $loan->load(['borrower', 'loanProduct', 'branch', 'schedule']);

        $data = [
            'loan'     => $loan,
            'borrower' => $loan->borrower,
            'schedule' => $loan->schedule,
            'company'  => [
                'name'    => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
                'address' => \App\Models\Setting::get('company_address', ''),
                'phone'   => \App\Models\Setting::get('company_phone', ''),
                'logo'    => \App\Models\Setting::get('company_logo', ''),
            ],
        ];

        $pdf  = Pdf::loadView('pdf.schedule', $data);
        $pdf->setPaper('A4', 'portrait');

        $path = 'schedules/' . $loan->loan_number . '-schedule.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Stream PDF response for browser viewing.
     */
    public function streamReceipt(Repayment $repayment): \Symfony\Component\HttpFoundation\Response
    {
        $repayment->load(['loan.borrower', 'collectedBy', 'branch']);

        $data = [
            'repayment' => $repayment,
            'loan'      => $repayment->loan,
            'borrower'  => $repayment->loan->borrower,
            'branch'    => $repayment->branch,
            'company'   => [
                'name'   => \App\Models\Setting::get('company_name', config('bigcash.company.name')),
                'address'=> \App\Models\Setting::get('company_address', ''),
                'phone'  => \App\Models\Setting::get('company_phone', ''),
                'email'  => \App\Models\Setting::get('company_email', ''),
                'logo'   => \App\Models\Setting::get('company_logo', ''),
                'footer' => \App\Models\Setting::get('receipt_footer', 'Thank you for your payment.'),
            ],
        ];

        $pdf = Pdf::loadView('pdf.receipt', $data)->setPaper('A5', 'portrait');
        return $pdf->stream($repayment->receipt_number . '.pdf');
    }
}
