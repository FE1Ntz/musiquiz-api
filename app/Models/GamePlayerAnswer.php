<?php

namespace App\Models;

use Database\Factories\GamePlayerAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'game_player_id',
    'game_round_id',
    'guessed_track_id',
    'text_guess',
    'answer_time_ms',
    'is_correct',
    'points_awarded',
])]
class GamePlayerAnswer extends Model
{
    /** @use HasFactory<GamePlayerAnswerFactory> */
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
     * Get the player who submitted this answer.
     */
    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    /**
     * Get the round this answer belongs to.
     */
    public function gameRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class);
    }

    /**
     * Get the guessed track.
     */
    public function guessedTrack(): BelongsTo
    {
        return $this->belongsTo(Track::class, 'guessed_track_id');
    }
}
