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
        Schema::create('game_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->text('preview_url')->nullable();
            $table->unsignedSmallInteger('round_number');
            $table->unsignedSmallInteger('snippet_start_second');
            $table->unsignedSmallInteger('snippet_end_second');
            $table->json('track_options')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_session_id', 'round_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};
