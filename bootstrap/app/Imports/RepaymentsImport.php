<?php

namespace App\Imports;

use App\Models\Loan;
use App\Models\Repayment;
use App\Services\Loan\RepaymentAllocationService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class RepaymentsImport implements ToCollection, WithHeadingRow
{
    protected array $result = ['success' => 0, 'failed' => 0, 'errors' => []];
    protected RepaymentAllocationService $allocationService;

    public function __construct(
        protected string $batchId,
        protected $uploadedBy
    ) {
        $this->allocationService = app(RepaymentAllocationService::class);
    }

    /**
     * Expected columns: loan_number, amount, payment_method, payment_date, reference, notes
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-indexed + header row
            try {
                $loanNumber = trim($row['loan_number'] ?? '');
                $amount     = (float) ($row['amount'] ?? 0);
                $method     = strtolower(trim($row['payment_method'] ?? 'cash'));
                $date       = $row['payment_date'] ?? now()->toDateString();

                if (empty($loanNumber) || $amount <= 0) {
                    $this->result['errors'][] = "Row {$rowNum}: Missing loan number or invalid amount.";
                    $this->result['failed']++;
                    continue;
                }

                $loan = Loan::where('loan_number', $loanNumber)->first();
                if (! $loan) {
                    $this->result['errors'][] = "Row {$rowNum}: Loan '{$loanNumber}' not found.";
                    $this->result['failed']++;
                    continue;
                }

                if (! $loan->isActive()) {
                    $this->result['errors'][] = "Row {$rowNum}: Loan '{$loanNumber}' is not active.";
                    $this->result['failed']++;
                    continue;
                }

                // Normalise method
                $validMethods = ['cash', 'mobile_money', 'bank_transfer', 'cheque', 'other'];
                if (!in_array($method, $validMethods)) $method = 'cash';

                $repayment = Repayment::create([
                    'receipt_number'    => Repayment::generateReceiptNumber(),
                    'loan_id'           => $loan->id,
                    'borrower_id'       => $loan->borrower_id,
                    'branch_id'         => $loan->branch_id,
                    'collected_by'      => $this->uploadedBy->id,
                    'amount'            => $amount,
                    'payment_method'    => $method,
                    'payment_reference' => trim($row['reference'] ?? ''),
                    'payment_date'      => $date,
                    'status'            => 'confirmed',
                    'notes'             => trim($row['notes'] ?? 'Bulk upload'),
                    'bulk_upload_batch' => $this->batchId,
                ]);

                $this->allocationService->allocate($loan, $amount, $repayment);
                $this->result['success']++;

            } catch (\Exception $e) {
                $this->result['errors'][] = "Row {$rowNum}: " . $e->getMessage();
                $this->result['failed']++;
            }
        }
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
