<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('address');
            $table->string('region')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('manager_name')->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
