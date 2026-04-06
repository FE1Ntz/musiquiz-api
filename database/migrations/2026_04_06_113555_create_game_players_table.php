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
        Schema::create('game_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nickname');
            $table->string('token', 64)->unique();
            $table->integer('score')->default(0);
            $table->unsignedSmallInteger('correct_answers_count')->default(0);
            $table->boolean('is_host')->default(false);
            $table->boolean('is_connected')->default(true);
            $table->timestamps();

            $table->index('token');
            $table->unique(['game_room_id', 'nickname']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};
