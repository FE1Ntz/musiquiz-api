<?php

namespace App\Models;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\GameStatus;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'guest_session_id',
    'artist_id',
    'difficulty',
    'answer_mode',
    'current_round',
    'total_rounds',
    'score',
    'correct_answers_count',
    'status',
    'started_at',
    'ended_at',
])]
class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
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
            'status' => GameStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this game session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the artist for this game session.
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Get the rounds for this game session.
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }

    /**
     * Get the answers for this game session.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }

    /**
     * Get the current active round.
     */
    public function currentRound(): ?GameRound
    {
        return $this->rounds()
            ->where('round_number', $this->current_round)
            ->first();
    }

    /**
     * Determine if the game session is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === GameStatus::InProgress;
    }

    /**
     * Determine if the game session is finished.
     */
    public function isFinished(): bool
    {
        return $this->status === GameStatus::Finished;
    }

    /**
     * Determine if the game uses multiple choice answers.
     */
    public function isMultipleChoice(): bool
    {
        return $this->answer_mode === AnswerMode::MultipleChoice;
    }
}
