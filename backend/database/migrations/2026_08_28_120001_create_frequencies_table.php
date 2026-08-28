<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('frequencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->foreignId('talking_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('ptt_uuid')->nullable()->unique();
            $table->timestamp('ptt_started_at')->nullable();
            $table->timestamp('ptt_last_seen_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = [];

        for ($number = 1; $number <= 99; $number++) {
            $rows[] = [
                'number' => $number,
                'talking_user_id' => null,
                'ptt_uuid' => null,
                'ptt_started_at' => null,
                'ptt_last_seen_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('frequencies')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frequencies');
    }
};
