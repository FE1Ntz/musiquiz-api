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
        Schema::create('game_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_session_id')->nullable();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('difficulty');
            $table->string('answer_mode')->default('multiple_choice');
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('total_rounds')->default(10);
            $table->integer('score')->default(0);
            $table->unsignedSmallInteger('correct_answers_count')->default(0);
            $table->string('status')->default('waiting');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('guest_session_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
