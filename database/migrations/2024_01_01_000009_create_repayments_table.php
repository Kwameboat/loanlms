<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('loan_id')->constrained('loans');
            $table->foreignId('borrower_id')->constrained('borrowers');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('collected_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->decimal('principal_paid', 12, 2)->default(0);
            $table->decimal('interest_paid', 12, 2)->default(0);
            $table->decimal('fees_paid', 12, 2)->default(0);
            $table->decimal('penalty_paid', 12, 2)->default(0);

            $table->enum('payment_method', [
                'cash', 'mobile_money', 'bank_transfer', 'cheque', 'paystack', 'other'
            ]);
            $table->string('payment_reference')->nullable();
            $table->string('mobile_money_number', 20)->nullable();
            $table->enum('mobile_money_provider', ['mtn', 'vodafone', 'airteltigo'])->nullable();
            $table->string('bank_name')->nullable();
            $table->string('cheque_number', 30)->nullable();

            // Paystack fields
            $table->string('paystack_reference')->nullable()->unique();
            $table->string('paystack_transaction_id')->nullable();
            $table->string('paystack_channel')->nullable();
            $table->decimal('paystack_fees', 10, 2)->default(0);
            $table->json('paystack_raw_response')->nullable();
            $table->enum('paystack_status', ['pending', 'success', 'failed', 'abandoned', 'reversed'])->nullable();

            $table->date('payment_date');
            $table->time('payment_time')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'reversed', 'failed'])->default('confirmed');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();

            // Link to installment(s) being paid
            $table->foreignId('repayment_schedule_id')->nullable()->constrained('repayment_schedules')->nullOnDelete();

            // Bulk upload ref
            $table->string('bulk_upload_batch', 30)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['payment_date']);
            $table->index(['paystack_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
