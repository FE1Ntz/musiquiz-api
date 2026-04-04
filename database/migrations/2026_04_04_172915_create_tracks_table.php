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
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deezer_id')->unique();
            $table->string('title');
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedSmallInteger('track_position')->nullable();
            $table->boolean('explicit_lyrics')->default(false);
            $table->string('isrc')->nullable()->index();
            $table->text('preview')->nullable();
            $table->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracks');
    }
};
