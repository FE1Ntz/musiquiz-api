<?php

namespace App\Models;

use Database\Factories\GameRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'game_session_id',
    'track_id',
    'round_number',
    'snippet_start_second',
    'snippet_end_second',
    'track_options',
    'preview_url',
    'is_completed',
    'started_at',
    'completed_at',
])]
class GameRound extends Model
{
    /** @use HasFactory<GameRoundFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'track_options' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the game session that owns this round.
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * Get the track for this round.
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * Get the answers for this round.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }
}
