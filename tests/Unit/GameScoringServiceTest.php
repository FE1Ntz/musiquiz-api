<?php

namespace Tests\Unit;

use App\Enums\Difficulty;
use App\Services\GameScoringService;
use PHPUnit\Framework\TestCase;

class GameScoringServiceTest extends TestCase
{
    private GameScoringService $scoringService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoringService = new GameScoringService;
    }

    public function test_correct_answer_awards_positive_points(): void
    {
        $points = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 5000);

        $this->assertGreaterThan(0, $points);
    }

    public function test_faster_answers_score_higher(): void
    {
        $fastPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 1000);
        $slowPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 25000);

        $this->assertGreaterThan($slowPoints, $fastPoints);
    }

    public function test_instant_answer_gets_maximum_points(): void
    {
        $points = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 0);

        $this->assertEquals(1000, $points); // BASE_POINTS * 1.0 (easy multiplier)
    }

    public function test_hard_difficulty_multiplier_is_higher(): void
    {
        $easyPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 5000);
        $hardPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Hard, 5000);

        $this->assertGreaterThan($easyPoints, $hardPoints);
    }

    public function test_medium_difficulty_multiplier(): void
    {
        $easyPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 5000);
        $mediumPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Medium, 5000);

        $this->assertGreaterThan($easyPoints, $mediumPoints);
    }

    public function test_wrong_answer_returns_negative_penalty(): void
    {
        $penalty = $this->scoringService->calculateWrongAnswerPenalty();

        $this->assertLessThan(0, $penalty);
        $this->assertEquals(-200, $penalty);
    }

    public function test_apply_points_adds_to_score(): void
    {
        $newScore = $this->scoringService->applyPoints(500, 300);

        $this->assertEquals(800, $newScore);
    }

    public function test_apply_points_subtracts_from_score(): void
    {
        $newScore = $this->scoringService->applyPoints(500, -200);

        $this->assertEquals(300, $newScore);
    }

    public function test_score_cannot_go_below_minimum(): void
    {
        $newScore = $this->scoringService->applyPoints(100, -500);

        $this->assertEquals(0, $newScore);
    }

    public function test_score_at_exactly_zero_stays_at_zero(): void
    {
        $newScore = $this->scoringService->applyPoints(0, -200);

        $this->assertEquals(0, $newScore);
    }

    public function test_answer_at_snippet_end_still_gives_minimum_points(): void
    {
        // Easy = 30 seconds = 30000ms, answering at the very end
        $points = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 30000);

        $this->assertGreaterThan(0, $points);
        $this->assertEquals(100, $points); // BASE_POINTS * 0.1 * 1.0
    }

    public function test_answer_time_exceeding_snippet_is_clamped(): void
    {
        // Answering after 60 seconds on an easy game (30s snippet) should clamp
        $points = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 60000);
        $endPoints = $this->scoringService->calculateCorrectAnswerPoints(Difficulty::Easy, 30000);

        $this->assertEquals($endPoints, $points);
    }
}
