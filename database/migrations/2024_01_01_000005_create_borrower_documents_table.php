<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrower_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->enum('document_type', [
                'ghana_card',
                'passport_photo',
                'payslip',
                'bank_statement',
                'business_registration',
                'utility_bill',
                'guarantor_id',
                'signed_loan_agreement',
                'property_document',
                'other',
            ]);
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_type', 20)->nullable();
            $table->integer('file_size')->nullable(); // bytes
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_documents');
    }
};
