<?php

namespace App\Http\Controllers;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Http\Requests\CreateRoomRequest;
use App\Http\Requests\JoinRoomRequest;
use App\Http\Requests\SubmitMultiplayerAnswerRequest;
use App\Http\Resources\GamePlayerResource;
use App\Http\Resources\GameRoomResource;
use App\Http\Resources\GameRoundResource;
use App\Models\Artist;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Services\MultiplayerGameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultiplayerGameController extends Controller
{
    public function __construct(
        private MultiplayerGameService $multiplayerService,
    ) {}

    /**
     * Create a new game room.
     */
    public function createRoom(CreateRoomRequest $request): JsonResponse
    {
        $artist = Artist::query()->findOrFail($request->validated('artist_id'));
        $difficulty = Difficulty::from($request->validated('difficulty'));
        $answerMode = AnswerMode::tryFrom($request->validated('answer_mode') ?? '') ?? AnswerMode::MultipleChoice;
        $maxPlayers = $request->validated('max_players') ?? 8;

        $result = $this->multiplayerService->createRoom(
            artist: $artist,
            difficulty: $difficulty,
            nickname: $request->validated('nickname'),
            answerMode: $answerMode,
            maxPlayers: $maxPlayers,
            user: $request->user(),
        );

        return response()->json([
            'room' => new GameRoomResource($result['room']),
            'player' => new GamePlayerResource($result['player']),
            'player_token' => $result['player']->token,
        ], 201);
    }

    /**
     * Join an existing room by code.
     */
    public function joinRoom(JoinRoomRequest $request, string $code): JsonResponse
    {
        $room = GameRoom::where('code', $code)->firstOrFail();

        $result = $this->multiplayerService->joinRoom(
            room: $room,
            nickname: $request->validated('nickname'),
            user: $request->user(),
        );

        return response()->json([
            'room' => new GameRoomResource($result['room']),
            'player' => new GamePlayerResource($result['player']),
            'player_token' => $result['player']->token,
        ]);
    }

    /**
     * Leave a room.
     */
    public function leaveRoom(Request $request, GameRoom $gameRoom): JsonResponse
    {
        $player = $this->resolvePlayer($request, $gameRoom);

        $this->multiplayerService->leaveRoom($gameRoom, $player);

        return response()->json(['message' => 'You have left the room.']);
    }

    /**
     * Show room details (read-only, useful for reconnection).
     */
    public function showRoom(GameRoom $gameRoom): JsonResponse
    {
        $gameRoom->load(['artist', 'players']);

        $response = [
            'room' => new GameRoomResource($gameRoom),
        ];

        // Include current round data and track options when game is in progress
        if ($gameRoom->isInProgress() && $gameRoom->gameSession) {
            $round = $gameRoom->gameSession->rounds()
                ->where('round_number', $gameRoom->current_round)
                ->first();

            if ($round) {
                $response['current_round'] = new GameRoundResource($round);

                if ($gameRoom->isMultipleChoice() && $round->track_options) {
                    $response['track_options'] = $round->track_options;
                }
            }
        }

        // Include leaderboard when game is finished
        if ($gameRoom->isFinished()) {
            $response['leaderboard'] = $this->multiplayerService->getLeaderboard($gameRoom);
        }

        return response()->json($response);
    }

    /**
     * Start the game (host only).
     */
    public function startGame(Request $request, GameRoom $gameRoom): JsonResponse
    {
        $player = $this->resolvePlayer($request, $gameRoom);

        $round = $this->multiplayerService->startGame($gameRoom, $player);

        $gameRoom->load(['artist', 'players']);

        $response = [
            'room' => new GameRoomResource($gameRoom),
            'current_round' => new GameRoundResource($round),
        ];

        if ($gameRoom->isMultipleChoice()) {
            $response['track_options'] = $round->track_options;
        }

        return response()->json($response);
    }

    /**
     * Submit an answer for the current round.
     */
    public function submitAnswer(SubmitMultiplayerAnswerRequest $request, GameRoom $gameRoom): JsonResponse
    {
        $player = $this->resolvePlayer($request, $gameRoom);

        $result = $this->multiplayerService->submitAnswer(
            room: $gameRoom,
            player: $player,
            guessedTrackId: $request->validated('guessed_track_id'),
            textGuess: $request->validated('text_guess'),
            answerTimeMs: $request->validated('answer_time_ms'),
        );

        return response()->json([
            'correct' => $result['is_correct'],
            'points_awarded' => $result['points_awarded'],
        ]);
    }

    /**
     * Handle a round timeout for a player.
     */
    public function timeout(Request $request, GameRoom $gameRoom): JsonResponse
    {
        $player = $this->resolvePlayer($request, $gameRoom);

        $this->multiplayerService->handleTimeout($gameRoom, $player);

        return response()->json([
            'correct' => false,
            'points_awarded' => 0,
            'timed_out' => true,
        ]);
    }

    /**
     * Get the leaderboard for the room.
     */
    public function leaderboard(GameRoom $gameRoom): JsonResponse
    {
        return response()->json([
            'leaderboard' => $this->multiplayerService->getLeaderboard($gameRoom),
        ]);
    }

    /**
     * Resolve the current player from the X-Player-Token header.
     */
    private function resolvePlayer(Request $request, GameRoom $gameRoom): GamePlayer
    {
        $token = $request->header('X-Player-Token');

        if (! $token) {
            abort(401, 'Missing X-Player-Token header.');
        }

        $player = $this->multiplayerService->resolvePlayerFromToken($gameRoom, $token);

        if (! $player) {
            abort(401, 'Invalid player token.');
        }

        return $player;
    }
}
