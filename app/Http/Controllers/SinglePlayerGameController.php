<?php

namespace App\Http\Controllers;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Http\Requests\CreateSinglePlayerGameRequest;
use App\Http\Resources\GameRoundResource;
use App\Http\Resources\GameSessionResource;
use App\Models\Artist;
use App\Models\GameSession;
use App\Services\GameSessionService;
use App\Services\GameTrackSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SinglePlayerGameController extends Controller
{
    public function __construct(
        private GameSessionService $gameSessionService,
        private GameTrackSelectionService $trackSelectionService,
    ) {}

    /**
     * Create a new single-player game session.
     */
    public function store(CreateSinglePlayerGameRequest $request): JsonResponse
    {
        $artist = Artist::query()->findOrFail($request->validated('artist_id'));
        $difficulty = Difficulty::from($request->validated('difficulty'));
        $answerMode = AnswerMode::tryFrom($request->validated('answer_mode') ?? '') ?? AnswerMode::MultipleChoice;

        $guestSessionId = $request->user() ? null : ($request->header('X-Guest-Session-Id') ?? (string) Str::uuid());

        $gameSession = $this->gameSessionService->createSinglePlayerSession(
            artist: $artist,
            difficulty: $difficulty,
            answerMode: $answerMode,
            user: $request->user(),
            guestSessionId: $guestSessionId,
        );

        // Automatically start the first round
        $firstRound = $this->gameSessionService->startNextRound($gameSession);

        $gameSession->load('artist');

        $response = [
            'game_session' => new GameSessionResource($gameSession),
            'current_round' => new GameRoundResource($firstRound),
        ];

        if ($gameSession->isMultipleChoice()) {
            $response['track_options'] = $this->trackSelectionService->buildTrackOptions($gameSession->artist, $firstRound);
        }

        return response()->json($response, 201);
    }

    /**
     * Show the current state of a game session.
     */
    public function show(GameSession $gameSession): JsonResponse
    {
        $state = $this->gameSessionService->getGameState($gameSession);

        return response()->json(['data' => $state]);
    }

    /**
     * Get the current game state with track options for multiple choice.
     */
    public function state(GameSession $gameSession): JsonResponse
    {
        $state = $this->gameSessionService->getGameState($gameSession);

        if (isset($state['current_round_data']) && $gameSession->isMultipleChoice()) {
            $currentRound = $gameSession->currentRound();

            if ($currentRound) {
                $state['track_options'] = $this->trackSelectionService->buildTrackOptions($gameSession->artist, $currentRound);
            }
        }

        return response()->json(['data' => $state]);
    }

    /**
     * Advance to the next round.
     */
    public function nextRound(GameSession $gameSession): JsonResponse
    {
        if ($gameSession->current_round >= $gameSession->total_rounds) {
            $this->gameSessionService->finishGame($gameSession);
            $gameSession->load('artist');

            return response()->json([
                'game_session' => new GameSessionResource($gameSession),
                'message' => 'Game finished.',
            ]);
        }

        $round = $this->gameSessionService->startNextRound($gameSession);
        $gameSession->load('artist');

        $response = [
            'game_session' => new GameSessionResource($gameSession),
            'current_round' => new GameRoundResource($round),
        ];

        if ($gameSession->isMultipleChoice()) {
            $response['track_options'] = $this->trackSelectionService->buildTrackOptions($gameSession->artist, $round);
        }

        return response()->json($response);
    }

    /**
     * Finish a game session early.
     */
    public function finish(GameSession $gameSession): JsonResponse
    {
        $this->gameSessionService->finishGame($gameSession);
        $gameSession->load('artist');

        return response()->json([
            'game_session' => new GameSessionResource($gameSession),
            'message' => 'Game session finished.',
        ]);
    }
}
