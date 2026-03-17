<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 30)->unique(); // e.g. LN-2024-00001
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('borrower_id')->constrained('borrowers');
            $table->foreignId('loan_product_id')->constrained('loan_products');
            $table->foreignId('loan_officer_id')->constrained('users');
            $table->foreignId('created_by')->constrained('users');

            // Application Details
            $table->text('loan_purpose')->nullable();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('disbursed_amount', 12, 2)->nullable();
            $table->integer('term_months');
            $table->enum('repayment_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'custom']);

            // Interest & Fees (snapshot at time of disbursement)
            $table->enum('interest_type', ['flat', 'reducing'])->default('flat');
            $table->decimal('interest_rate', 8, 4);
            $table->decimal('processing_fee_amount', 12, 2)->default(0);
            $table->decimal('insurance_fee_amount', 12, 2)->default(0);
            $table->decimal('admin_fee_amount', 12, 2)->default(0);
            $table->decimal('other_fees_amount', 12, 2)->default(0);

            // Calculated Amounts
            $table->decimal('total_interest', 12, 2)->default(0);
            $table->decimal('total_repayable', 12, 2)->default(0);
            $table->decimal('installment_amount', 12, 2)->default(0);

            // Balances (updated in real-time)
            $table->decimal('outstanding_principal', 12, 2)->default(0);
            $table->decimal('outstanding_interest', 12, 2)->default(0);
            $table->decimal('outstanding_fees', 12, 2)->default(0);
            $table->decimal('outstanding_penalty', 12, 2)->default(0);
            $table->decimal('total_outstanding', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('total_interest_paid', 12, 2)->default(0);
            $table->decimal('total_penalty_paid', 12, 2)->default(0);

            // Schedule
            $table->date('application_date');
            $table->date('first_repayment_date')->nullable();
            $table->date('disbursement_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('actual_completion_date')->nullable();

            // Status
            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'pending_documents',
                'recommended', 'approved', 'rejected', 'disbursed', 'active',
                'overdue', 'completed', 'defaulted', 'written_off', 'rescheduled'
            ])->default('draft');
            $table->integer('days_past_due')->default(0);
            $table->boolean('is_overdue')->default(false);
            $table->date('overdue_since')->nullable();

            // Disbursement Details
            $table->enum('disbursement_method', ['cash', 'bank_transfer', 'mobile_money', 'cheque', 'paystack'])->nullable();
            $table->string('disbursement_bank')->nullable();
            $table->string('disbursement_account')->nullable();
            $table->string('disbursement_reference')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accountant_verified_by')->nullable()->constrained('users')->nullOnDelete();

            // Approval Chain
            $table->foreignId('recommended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recommended_at')->nullable();
            $table->text('recommendation_note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->foreignId('second_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('second_approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Credit Assessment
            $table->decimal('debt_to_income_ratio', 8, 4)->nullable();
            $table->decimal('affordability_score', 8, 2)->nullable();
            $table->text('credit_assessment_notes')->nullable();
            $table->decimal('existing_debt_monthly', 12, 2)->nullable();

            // Write-off / Settlement
            $table->decimal('write_off_amount', 12, 2)->nullable();
            $table->foreignId('written_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('written_off_at')->nullable();
            $table->text('write_off_reason')->nullable();
            $table->decimal('waiver_amount', 12, 2)->default(0);
            $table->text('waiver_reason')->nullable();

            // Group loan
            $table->foreignId('group_loan_id')->nullable()->constrained('loans')->nullOnDelete();

            $table->text('internal_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['borrower_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['loan_officer_id']);
            $table->index(['status']);
            $table->index(['is_overdue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
