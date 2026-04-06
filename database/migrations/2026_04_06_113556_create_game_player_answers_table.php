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
        Schema::create('game_player_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guessed_track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->string('text_guess')->nullable();
            $table->unsignedInteger('answer_time_ms');
            $table->boolean('is_correct');
            $table->integer('points_awarded')->default(0);
            $table->timestamps();

            $table->unique(['game_player_id', 'game_round_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_player_answers');
    }
};
