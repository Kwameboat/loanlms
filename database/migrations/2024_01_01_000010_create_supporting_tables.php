<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loan Documents
        Schema::create('loan_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('document_type', [
                'loan_application_form', 'id_document', 'payslip',
                'bank_statement', 'business_registration', 'signed_agreement',
                'disbursement_voucher', 'other'
            ]);
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_type', 20)->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamps();
        });

        // Loan Status History
        Schema::create('loan_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['loan_id']);
        });

        // Penalty Transactions
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('repayment_schedule_id')->nullable()->constrained('repayment_schedules')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->integer('days_overdue');
            $table->date('accrual_date');
            $table->enum('status', ['outstanding', 'paid', 'waived'])->default('outstanding');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('waived_amount', 12, 2)->default(0);
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
        });

        // Notification Log
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('borrower_id')->nullable()->constrained('borrowers')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->enum('channel', ['email', 'sms', 'system']);
            $table->string('template_type', 60);
            $table->string('recipient', 120);
            $table->text('message');
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // System Settings (key-value store)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('group', 60)->default('general');
            $table->string('label')->nullable();
            $table->string('type', 30)->default('text'); // text, boolean, json, file
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        // Paystack Payment Links
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans');
            $table->foreignId('borrower_id')->constrained('borrowers');
            $table->foreignId('repayment_schedule_id')->nullable()->constrained('repayment_schedules')->nullOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('paystack_access_code')->nullable();
            $table->string('authorization_url')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('email', 120);
            $table->enum('purpose', ['installment', 'penalty', 'full_settlement', 'partial'])->default('installment');
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['reference']);
        });

        // Borrower Notes (internal staff comments)
        Schema::create('borrower_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->text('note');
            $table->enum('type', ['general', 'warning', 'positive', 'collection', 'legal'])->default('general');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
        });

        // Simple Ledger Entries
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignId('repayment_id')->nullable()->constrained('repayments')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->enum('entry_type', [
                'loan_disbursement', 'repayment_received', 'penalty_posted',
                'waiver_granted', 'write_off', 'fee_income', 'interest_income', 'adjustment'
            ]);
            $table->enum('debit_credit', ['debit', 'credit']);
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->date('entry_date');
            $table->string('reference', 60)->nullable();
            $table->timestamps();

            $table->index(['entry_date', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('borrower_notes');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('penalties');
        Schema::dropIfExists('loan_status_history');
        Schema::dropIfExists('loan_documents');
    }
};
