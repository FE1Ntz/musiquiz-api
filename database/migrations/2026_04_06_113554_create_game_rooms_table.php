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
        Schema::create('game_rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 6)->unique();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('difficulty');
            $table->string('answer_mode')->default('multiple_choice');
            $table->string('status')->default('waiting_for_players');
            $table->unsignedSmallInteger('max_players')->default(8);
            $table->foreignId('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('total_rounds')->default(10);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rooms');
    }
};
