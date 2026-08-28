<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('frequency_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frequency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['frequency_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frequency_signals');
    }
};
