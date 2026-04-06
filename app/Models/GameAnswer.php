<?php

namespace App\Models;

use Database\Factories\GameAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'game_round_id',
    'game_session_id',
    'guessed_track_id',
    'text_guess',
    'answer_time_ms',
    'is_correct',
    'points_awarded',
])]
class GameAnswer extends Model
{
    /** @use HasFactory<GameAnswerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * Get the round this answer belongs to.
     */
    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class);
    }

    /**
     * Get the game session this answer belongs to.
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * Get the guessed track.
     */
    public function guessedTrack(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'guessed_track_id');
    }
}
