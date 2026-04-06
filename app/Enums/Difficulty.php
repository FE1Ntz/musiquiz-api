<?php

namespace App\Enums;

enum Difficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    /**
     * Get the snippet length in seconds for this difficulty.
     */
    public function snippetLengthSeconds(): int
    {
        return match ($this) {
            self::Easy => 30,
            self::Medium => 15,
            self::Hard => 10,
        };
    }

    /**
     * Get the time limit in seconds the player has to answer after the snippet plays.
     */
    public function answerTimeLimitSeconds(): int
    {
        return match ($this) {
            self::Easy => 30,
            self::Medium => 15,
            self::Hard => 10
        };
    }
}
