<?php

namespace App\Services;

use App\Enums\Difficulty;

class GameScoringService
{
    /**
     * Base points awarded for a correct answer.
     */
    private const int BASE_POINTS = 1000;

    /**
     * Points subtracted for a wrong answer.
     */
    private const int WRONG_ANSWER_PENALTY = 200;

    /**
     * Minimum score a player can have (floor).
     */
    private const int MINIMUM_SCORE = 0;

    /**
     * Difficulty multipliers for scoring.
     *
     * @var array<string, float>
     */
    private const array DIFFICULTY_MULTIPLIERS = [
        'easy' => 1.0,
        'medium' => 1.5,
        'hard' => 2.0,
    ];

    /**
     * Calculate points for a correct answer based on speed and difficulty.
     *
     * Faster answers yield more points. The score scales linearly from
     * BASE_POINTS (instant answer) down to a minimum fraction of BASE_POINTS
     * (answered at the very last moment).
     */
    public function calculateCorrectAnswerPoints(Difficulty $difficulty, int $answerTimeMs): int
    {
        $snippetLengthMs = $difficulty->snippetLengthSeconds() * 1000;
        $clampedTime = max(0, min($answerTimeMs, $snippetLengthMs));

        // Speed ratio: 1.0 = instant, 0.0 = last moment
        $speedRatio = 1.0 - ($clampedTime / $snippetLengthMs);

        // Minimum 10% of base points for a correct answer at the last moment
        $speedMultiplier = 0.1 + (0.9 * $speedRatio);

        $difficultyMultiplier = self::DIFFICULTY_MULTIPLIERS[$difficulty->value] ?? 1.0;

        return (int) round(self::BASE_POINTS * $speedMultiplier * $difficultyMultiplier);
    }

    /**
     * Calculate the penalty for a wrong answer.
     */
    public function calculateWrongAnswerPenalty(): int
    {
        return -self::WRONG_ANSWER_PENALTY;
    }

    /**
     * Apply points to a current score, respecting the minimum score floor.
     */
    public function applyPoints(int $currentScore, int $pointsDelta): int
    {
        return max(self::MINIMUM_SCORE, $currentScore + $pointsDelta);
    }
}
