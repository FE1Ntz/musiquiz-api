<?php

namespace App\Models;

use Database\Factories\GamePlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'game_room_id',
    'user_id',
    'nickname',
    'token',
    'score',
    'correct_answers_count',
    'is_host',
    'is_connected',
])]
class GamePlayer extends Model
{
    /** @use HasFactory<GamePlayerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_connected' => 'boolean',
        ];
    }

    /**
     * Get the room this player belongs to.
     */
    public function gameRoom(): BelongsTo
    {
        return $this->belongsTo(GameRoom::class);
    }

    /**
     * Get the registered user (if linked).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all answers by this player.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(GamePlayerAnswer::class);
    }

    /**
     * Determine if this player is a guest (no linked user).
     */
    public function isGuest(): bool
    {
        return $this->user_id === null;
    }
}
