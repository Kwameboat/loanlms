<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('created_by')->constrained('users');
            $table->string('borrower_number', 30)->unique(); // e.g. BRW-2024-0001

            // Personal Information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_names')->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth');
            $table->string('ghana_card_number', 50)->nullable()->unique();
            $table->string('voter_id', 50)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->string('nationality', 60)->default('Ghanaian');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->integer('number_of_dependants')->default(0);

            // Contact Information
            $table->string('primary_phone', 20);
            $table->string('secondary_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp_number', 20)->nullable();

            // Residential Address
            $table->text('residential_address');
            $table->string('digital_address', 30)->nullable(); // GhanaPostGPS
            $table->string('nearest_landmark')->nullable();
            $table->string('region', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('town_city', 60)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Employment / Income
            $table->enum('employment_status', [
                'employed', 'self_employed', 'business_owner', 'unemployed', 'student', 'retired'
            ]);
            $table->string('occupation')->nullable();
            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_phone', 20)->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();

            // Business Details (if self-employed / business owner)
            $table->string('business_name')->nullable();
            $table->string('business_registration_number', 60)->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_type')->nullable();
            $table->decimal('monthly_business_revenue', 12, 2)->nullable();

            // Next of Kin
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_relationship', 60)->nullable();
            $table->string('next_of_kin_phone', 20)->nullable();
            $table->text('next_of_kin_address')->nullable();

            // Bank Details
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('account_name')->nullable();
            $table->string('mobile_money_number', 20)->nullable();
            $table->enum('mobile_money_provider', ['mtn', 'vodafone', 'airteltigo'])->nullable();

            // Profile
            $table->string('photo')->nullable();
            $table->enum('status', ['active', 'blacklisted', 'deceased', 'inactive'])->default('active');
            $table->text('blacklist_reason')->nullable();
            $table->decimal('credit_score', 5, 2)->nullable();
            $table->text('internal_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['primary_phone']);
            $table->index(['ghana_card_number']);
            $table->index(['branch_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
