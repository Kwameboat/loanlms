<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->enum('product_type', [
                'salary_loan', 'personal_loan', 'business_loan',
                'emergency_loan', 'group_loan', 'microloan', 'other'
            ]);
            $table->decimal('min_amount', 12, 2)->default(100.00);
            $table->decimal('max_amount', 12, 2)->default(50000.00);
            $table->integer('min_term')->default(1);  // months
            $table->integer('max_term')->default(36); // months
            $table->enum('interest_type', ['flat', 'reducing'])->default('flat');
            $table->decimal('interest_rate', 8, 4);  // percentage per period
            $table->enum('interest_period', ['per_annum', 'per_month', 'per_week'])->default('per_month');
            $table->decimal('processing_fee', 8, 4)->default(0); // percentage of loan
            $table->decimal('processing_fee_amount', 12, 2)->nullable(); // fixed amount (overrides %)
            $table->decimal('insurance_fee', 8, 4)->default(0); // percentage
            $table->decimal('insurance_fee_amount', 12, 2)->nullable();
            $table->decimal('admin_fee', 8, 4)->default(0);
            $table->decimal('admin_fee_amount', 12, 2)->nullable();
            $table->integer('grace_period_days')->default(0);

            // Repayment
            $table->enum('repayment_frequency', ['daily', 'weekly', 'biweekly', 'monthly', 'custom'])
                  ->default('monthly');
            $table->boolean('allow_partial_payment')->default(true);
            $table->boolean('allow_early_repayment')->default(true);
            $table->decimal('early_repayment_fee', 8, 4)->default(0); // % of remaining principal

            // Penalty
            $table->boolean('penalty_enabled')->default(true);
            $table->enum('penalty_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('penalty_rate', 8, 4)->default(2); // % per month of overdue
            $table->decimal('penalty_fixed_amount', 12, 2)->nullable();
            $table->integer('penalty_grace_days')->default(0); // days after due before penalty

            // Eligibility
            $table->integer('min_age')->default(18);
            $table->integer('max_age')->default(70);
            $table->decimal('min_monthly_income', 12, 2)->nullable();
            $table->json('eligible_employment_types')->nullable();
            $table->boolean('requires_guarantor')->default(false);
            $table->text('eligibility_notes')->nullable();

            // Settings
            $table->boolean('requires_approval_chain')->default(true);
            $table->integer('approval_levels')->default(1); // 1 or 2
            $table->boolean('is_active')->default(true);
            $table->boolean('is_group_loan')->default(false);
            $table->json('required_documents')->nullable();
            $table->text('terms_and_conditions')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('branch_loan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'loan_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_loan_products');
        Schema::dropIfExists('loan_products');
    }
};
