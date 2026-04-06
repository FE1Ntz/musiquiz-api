<?php

namespace App\Models;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\RoomStatus;
use Database\Factories\GameRoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'artist_id',
    'difficulty',
    'answer_mode',
    'status',
    'max_players',
    'game_session_id',
    'current_round',
    'total_rounds',
    'started_at',
    'finished_at',
])]
class GameRoom extends Model
{
    /** @use HasFactory<GameRoomFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'answer_mode' => AnswerMode::class,
            'status' => RoomStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Get the artist for this room.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the game session once the game starts.
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * Get all players in this room.
     */
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    /**
     * Get the host player.
     */
    public function host(): ?GamePlayer
    {
        return $this->players()->where('is_host', true)->first();
    }

    /**
     * Determine if the room is waiting for players.
     */
    public function isWaitingForPlayers(): bool
    {
        return $this->status === RoomStatus::WaitingForPlayers;
    }

    /**
     * Determine if the room is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === RoomStatus::InProgress;
    }

    /**
     * Determine if the room is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === RoomStatus::Finished;
    }

    /**
     * Determine if the room is full.
     */
    public function isFull(): bool
    {
        return $this->players()->count() >= $this->max_players;
    }

    /**
     * Determine if the game uses multiple choice answers.
     */
    public function isMultipleChoice(): bool
    {
        return $this->answer_mode === AnswerMode::MultipleChoice;
    }
}
