<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('principal_due', 12, 2)->default(0);
            $table->decimal('interest_due', 12, 2)->default(0);
            $table->decimal('fees_due', 12, 2)->default(0);
            $table->decimal('penalty_due', 12, 2)->default(0);
            $table->decimal('total_due', 12, 2)->default(0);
            $table->decimal('closing_balance', 12, 2)->default(0);

            // Paid amounts
            $table->decimal('principal_paid', 12, 2)->default(0);
            $table->decimal('interest_paid', 12, 2)->default(0);
            $table->decimal('fees_paid', 12, 2)->default(0);
            $table->decimal('penalty_paid', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);

            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'waived'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->integer('days_past_due')->default(0);
            $table->boolean('is_overdue')->default(false);
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayment_schedules');
    }
};
