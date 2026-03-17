<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->text('p256dh_key');
            $table->text('auth_token');
            $table->string('device_type', 30)->nullable(); // android, ios, desktop
            $table->string('browser', 30)->nullable();     // chrome, safari, firefox
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'endpoint'], 'user_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
