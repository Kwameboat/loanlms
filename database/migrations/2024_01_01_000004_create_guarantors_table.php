<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('ghana_card_number', 50)->nullable();
            $table->string('relationship', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->string('photo')->nullable();
            $table->string('id_document')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantors');
    }
};
