<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitAnswerRequest;
use App\Models\GameSession;
use App\Services\GameSessionService;
use Illuminate\Http\JsonResponse;

class GameAnswerController extends Controller
{
    public function __construct(private GameSessionService $gameSessionService) {}

    /**
     * Submit an answer for the current round.
     */
    public function store(SubmitAnswerRequest $request, GameSession $gameSession): JsonResponse
    {
        $result = $this->gameSessionService->submitAnswer(
            gameSession: $gameSession,
            guessedTrackId: $request->validated('guessed_track_id'),
            textGuess: $request->validated('text_guess'),
            answerTimeMs: $request->validated('answer_time_ms'),
        );

        return response()->json([
            'correct' => $result['is_correct'],
            'points_awarded' => $result['points_awarded'],
            'score_delta' => $result['points_awarded'],
            'updated_total_score' => $result['updated_total_score'],
            'round_finished' => true,
        ]);
    }

    /**
     * Handle a round timeout — the player didn't answer in time.
     */
    public function timeout(GameSession $gameSession): JsonResponse
    {
        $result = $this->gameSessionService->skipRound($gameSession);

        return response()->json([
            'correct' => false,
            'points_awarded' => 0,
            'score_delta' => 0,
            'updated_total_score' => $result['updated_total_score'],
            'round_finished' => true,
            'timed_out' => true,
        ]);
    }
}
