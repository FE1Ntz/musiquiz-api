<?php

namespace App\Services;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\GameStatus;
use App\Enums\RoomStatus;
use App\Events\MultiplayerGameFinished;
use App\Events\MultiplayerRoundResults;
use App\Events\MultiplayerRoundStarted;
use App\Events\PlayerJoinedRoom;
use App\Events\PlayerLeftRoom;
use App\Jobs\AdvanceToNextRound;
use App\Jobs\ProcessRoundTimeout;
use App\Models\Artist;
use App\Models\GamePlayer;
use App\Models\GamePlayerAnswer;
use App\Models\GameRoom;
use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MultiplayerGameService
{
    private const int DEFAULT_TOTAL_ROUNDS = 10;

    private const int RESULTS_DISPLAY_DELAY_SECONDS = 3;

    private const int TIMEOUT_GRACE_PERIOD_SECONDS = 5;

    public function __construct(
        private GameTrackSelectionService $trackSelectionService,
        private SnippetTimingService $snippetTimingService,
        private GameScoringService $scoringService,
        private DeezerApiService $deezerApi,
    ) {}

    /**
     * Create a new game room and add the host as the first player.
     *
     * @return array{room: GameRoom, player: GamePlayer}
     *
     * @throws ValidationException
     */
    public function createRoom(
        Artist $artist,
        Difficulty $difficulty,
        string $nickname,
        AnswerMode $answerMode = AnswerMode::MultipleChoice,
        int $maxPlayers = 8,
        ?User $user = null,
    ): array {
        $availableTrackCount = $this->trackSelectionService->countAvailableTracks($artist);

        if ($availableTrackCount === 0) {
            throw ValidationException::withMessages([
                'artist_id' => ['This artist has no playable tracks.'],
            ]);
        }

        $totalRounds = min(self::DEFAULT_TOTAL_ROUNDS, $availableTrackCount);

        return DB::transaction(function () use ($artist, $difficulty, $answerMode, $nickname, $maxPlayers, $totalRounds, $user): array {
            $room = GameRoom::create([
                'code' => $this->generateUniqueCode(),
                'artist_id' => $artist->id,
                'difficulty' => $difficulty,
                'answer_mode' => $answerMode,
                'status' => RoomStatus::WaitingForPlayers,
                'max_players' => $maxPlayers,
                'total_rounds' => $totalRounds,
            ]);

            $player = GamePlayer::create([
                'game_room_id' => $room->id,
                'user_id' => $user?->id,
                'nickname' => $nickname,
                'token' => Str::random(64),
                'is_host' => true,
            ]);

            $room->load(['artist', 'players']);

            event(new PlayerJoinedRoom($room, $player));

            return ['room' => $room, 'player' => $player];
        });
    }

    /**
     * Join an existing room by code.
     *
     * @return array{room: GameRoom, player: GamePlayer}
     *
     * @throws ValidationException
     */
    public function joinRoom(
        GameRoom $room,
        string $nickname,
        ?User $user = null,
    ): array {
        if (! $room->isWaitingForPlayers()) {
            throw ValidationException::withMessages([
                'room' => ['This room is no longer accepting players.'],
            ]);
        }

        if ($room->isFull()) {
            throw ValidationException::withMessages([
                'room' => ['This room is full.'],
            ]);
        }

        $existingNickname = $room->players()
            ->where('nickname', $nickname)
            ->exists();

        if ($existingNickname) {
            throw ValidationException::withMessages([
                'nickname' => ['This nickname is already taken in this room.'],
            ]);
        }

        $player = GamePlayer::create([
            'game_room_id' => $room->id,
            'user_id' => $user?->id,
            'nickname' => $nickname,
            'token' => Str::random(64),
            'is_host' => false,
        ]);

        $room->load(['artist', 'players']);

        event(new PlayerJoinedRoom($room, $player));

        return ['room' => $room, 'player' => $player];
    }

    /**
     * Remove a player from a room.
     *
     * @throws ValidationException
     */
    public function leaveRoom(GameRoom $room, GamePlayer $player): void
    {
        if ($room->isInProgress()) {
            $player->update(['is_connected' => false]);
        } else {
            $player->delete();
        }

        event(new PlayerLeftRoom($room, $player));

        // If host leaves during lobby, cancel the room
        if ($player->is_host && $room->isWaitingForPlayers()) {
            $room->update(['status' => RoomStatus::Cancelled]);
        }
    }

    /**
     * Start the game — only the host can do this.
     *
     * @throws ValidationException
     */
    public function startGame(GameRoom $room, GamePlayer $hostPlayer): GameRound
    {
        if (! $hostPlayer->is_host) {
            throw ValidationException::withMessages([
                'player' => ['Only the host can start the game.'],
            ]);
        }

        // Allow restarting a finished game
        if ($room->isFinished()) {
            $this->resetRoom($room);
        }

        if (! $room->isWaitingForPlayers()) {
            throw ValidationException::withMessages([
                'room' => ['The game has already started or finished.'],
            ]);
        }

        $connectedPlayerCount = $room->players()->where('is_connected', true)->count();

        if ($connectedPlayerCount < 2) {
            throw ValidationException::withMessages([
                'room' => ['At least 2 players are required to start.'],
            ]);
        }

        $artist = $room->artist;

        return DB::transaction(function () use ($room, $artist): GameRound {
            // Create a GameSession to reuse rounds infrastructure
            $gameSession = GameSession::create([
                'user_id' => null,
                'guest_session_id' => null,
                'artist_id' => $artist->id,
                'difficulty' => $room->difficulty,
                'answer_mode' => $room->answer_mode,
                'current_round' => 0,
                'total_rounds' => $room->total_rounds,
                'score' => 0,
                'status' => GameStatus::InProgress,
                'started_at' => now(),
            ]);

            $tracks = $this->trackSelectionService->selectTracksForGame($artist, $room->total_rounds);

            foreach ($tracks as $index => $track) {
                $roundNumber = $index + 1;
                $timing = $this->snippetTimingService->calculateSnippetTiming(
                    $room->difficulty,
                    $track->id,
                    $roundNumber,
                );

                GameRound::create([
                    'game_session_id' => $gameSession->id,
                    'track_id' => $track->id,
                    'round_number' => $roundNumber,
                    'snippet_start_second' => $timing['snippet_start_second'],
                    'snippet_end_second' => $timing['snippet_end_second'],
                ]);
            }

            $room->update([
                'game_session_id' => $gameSession->id,
                'status' => RoomStatus::InProgress,
                'started_at' => now(),
            ]);

            // Start first round
            return $this->startNextRound($room);
        });
    }

    /**
     * Start the next round for the room.
     *
     * @throws ValidationException
     */
    public function startNextRound(GameRoom $room): GameRound
    {
        $room->refresh();
        $gameSession = $room->gameSession;

        if (! $gameSession) {
            throw ValidationException::withMessages([
                'room' => ['The game has not started yet.'],
            ]);
        }

        $nextRoundNumber = $room->current_round + 1;

        if ($nextRoundNumber > $room->total_rounds) {
            $this->finishGame($room);

            throw ValidationException::withMessages([
                'room' => ['No more rounds available.'],
            ]);
        }

        $round = $gameSession->rounds()
            ->where('round_number', $nextRoundNumber)
            ->firstOrFail();

        $previewUrl = $this->fetchPreviewUrl($round->track);

        $round->update([
            'started_at' => now(),
            'preview_url' => $previewUrl,
        ]);

        $room->update(['current_round' => $nextRoundNumber]);

        $gameSession->update(['current_round' => $nextRoundNumber]);

        $room->refresh();

        // Generate track options before broadcasting so all players receive them
        if ($room->isMultipleChoice()) {
            $this->trackSelectionService->buildTrackOptions($room->artist, $round);
            $round->refresh();
        }

        event(new MultiplayerRoundStarted($room, $round));

        // Schedule automatic timeout for this round
        $timeoutDelay = $room->difficulty->answerTimeLimitSeconds() + self::TIMEOUT_GRACE_PERIOD_SECONDS;
        ProcessRoundTimeout::dispatch($room, $nextRoundNumber)
            ->afterCommit()
            ->delay(now()->addSeconds($timeoutDelay));

        return $round;
    }

    /**
     * Submit an answer for the current round from a specific player.
     *
     * @return array{answer: GamePlayerAnswer, is_correct: bool, points_awarded: int}
     *
     * @throws ValidationException
     */
    public function submitAnswer(
        GameRoom $room,
        GamePlayer $player,
        ?int $guessedTrackId,
        ?string $textGuess,
        int $answerTimeMs,
    ): array {
        if (! $room->isInProgress()) {
            throw ValidationException::withMessages([
                'room' => ['This game is not in progress.'],
            ]);
        }

        $gameSession = $room->gameSession;
        $currentRound = $gameSession->rounds()
            ->where('round_number', $room->current_round)
            ->first();

        if (! $currentRound || $currentRound->is_completed) {
            throw ValidationException::withMessages([
                'round' => ['No active round to answer.'],
            ]);
        }

        // Check if this player already answered this round
        $existingAnswer = GamePlayerAnswer::where('game_player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->exists();

        if ($existingAnswer) {
            throw ValidationException::withMessages([
                'round' => ['You have already answered this round.'],
            ]);
        }

        // Check time limit (with 5s grace period for network latency)
        $timeLimitSeconds = $room->difficulty->answerTimeLimitSeconds();
        $deadline = $currentRound->started_at->addSeconds($timeLimitSeconds + 5);

        if (now()->greaterThan($deadline)) {
            throw ValidationException::withMessages([
                'round' => ['The time limit for this round has expired.'],
            ]);
        }

        $isCorrect = $this->evaluateAnswer($currentRound, $guessedTrackId, $textGuess);

        // Calculate answer time server-side from round start — don't trust client
        $serverAnswerTimeMs = (int) abs(now()->diffInMilliseconds($currentRound->started_at));

        $pointsAwarded = $isCorrect
            ? $this->scoringService->calculateCorrectAnswerPoints($room->difficulty, $serverAnswerTimeMs)
            : $this->scoringService->calculateWrongAnswerPenalty();

        $updatedScore = $this->scoringService->applyPoints($player->score, $pointsAwarded);

        return DB::transaction(function () use (
            $room,
            $player,
            $currentRound,
            $guessedTrackId,
            $textGuess,
            $serverAnswerTimeMs,
            $isCorrect,
            $pointsAwarded,
            $updatedScore,
        ): array {
            $answer = GamePlayerAnswer::create([
                'game_player_id' => $player->id,
                'game_round_id' => $currentRound->id,
                'guessed_track_id' => $guessedTrackId,
                'text_guess' => $textGuess,
                'answer_time_ms' => $serverAnswerTimeMs,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
            ]);

            $player->update([
                'score' => $updatedScore,
                'correct_answers_count' => $player->correct_answers_count + ($isCorrect ? 1 : 0),
            ]);

            // Check if all connected players have answered
            $this->checkRoundCompletion($room, $currentRound);

            return [
                'answer' => $answer,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
            ];
        });
    }

    /**
     * Handle a player's timeout — they didn't answer in time.
     *
     * @return array{answer: GamePlayerAnswer, is_correct: bool, points_awarded: int}
     *
     * @throws ValidationException
     */
    public function handleTimeout(GameRoom $room, GamePlayer $player): array
    {
        if (! $room->isInProgress()) {
            throw ValidationException::withMessages([
                'room' => ['This game is not in progress.'],
            ]);
        }

        $gameSession = $room->gameSession;
        $currentRound = $gameSession->rounds()
            ->where('round_number', $room->current_round)
            ->first();

        if (! $currentRound || $currentRound->is_completed) {
            throw ValidationException::withMessages([
                'round' => ['No active round.'],
            ]);
        }

        $existingAnswer = GamePlayerAnswer::where('game_player_id', $player->id)
            ->where('game_round_id', $currentRound->id)
            ->exists();

        if ($existingAnswer) {
            throw ValidationException::withMessages([
                'round' => ['You have already answered this round.'],
            ]);
        }

        return DB::transaction(function () use ($room, $player, $currentRound): array {
            $answer = GamePlayerAnswer::create([
                'game_player_id' => $player->id,
                'game_round_id' => $currentRound->id,
                'guessed_track_id' => null,
                'text_guess' => null,
                'answer_time_ms' => $room->difficulty->answerTimeLimitSeconds() * 1000,
                'is_correct' => false,
                'points_awarded' => 0,
            ]);

            $this->checkRoundCompletion($room, $currentRound);

            return [
                'answer' => $answer,
                'is_correct' => false,
                'points_awarded' => 0,
            ];
        });
    }

    /**
     * Check if all connected players have answered the current round.
     * If so, complete the round, broadcast results, and schedule
     * automatic advancement to the next round.
     */
    public function checkRoundCompletion(GameRoom $room, GameRound $round): void
    {
        $connectedPlayerIds = $room->players()
            ->where('is_connected', true)
            ->pluck('id');

        $answerCount = GamePlayerAnswer::where('game_round_id', $round->id)
            ->whereIn('game_player_id', $connectedPlayerIds)
            ->count();

        if ($answerCount < $connectedPlayerIds->count()) {
            return;
        }

        // All players answered — complete the round
        $round->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Build results for broadcasting
        $playerResults = $this->buildRoundResults($room, $round);

        event(new MultiplayerRoundResults($room, $round, $playerResults));

        // Schedule automatic advancement after results display delay
        AdvanceToNextRound::dispatch($room, $round->round_number)
            ->afterCommit()
            ->delay(now()->addSeconds(self::RESULTS_DISPLAY_DELAY_SECONDS));
    }

    /**
     * Build round results for all players.
     *
     * @return array<int, array{player_id: int, nickname: string, is_correct: bool, points_awarded: int, answer_time_ms: int, total_score: int}>
     */
    private function buildRoundResults(GameRoom $room, GameRound $round): array
    {
        $players = $room->players()->get();
        $answers = GamePlayerAnswer::where('game_round_id', $round->id)
            ->get()
            ->keyBy('game_player_id');

        return $players->map(function (GamePlayer $player) use ($answers) {
            $answer = $answers->get($player->id);

            return [
                'player_id' => $player->id,
                'nickname' => $player->nickname,
                'is_correct' => $answer?->is_correct ?? false,
                'points_awarded' => $answer?->points_awarded ?? 0,
                'answer_time_ms' => $answer?->answer_time_ms ?? 0,
                'total_score' => $player->fresh()->score,
            ];
        })->values()->all();
    }

    /**
     * Finish the game and broadcast final results.
     */
    public function finishGame(GameRoom $room): void
    {
        if ($room->isFinished()) {
            return;
        }

        // Complete any incomplete rounds
        if ($room->gameSession) {
            $room->gameSession->rounds()
                ->where('is_completed', false)
                ->update([
                    'is_completed' => true,
                    'completed_at' => now(),
                ]);

            $room->gameSession->update([
                'status' => GameStatus::Finished,
                'ended_at' => now(),
            ]);
        }

        $room->update([
            'status' => RoomStatus::Finished,
            'finished_at' => now(),
        ]);

        $room->refresh();

        event(new MultiplayerGameFinished($room));
    }

    /**
     * Get the leaderboard for the room.
     *
     * @return array<int, array{player_id: int, nickname: string, score: int, correct_answers_count: int, is_host: bool}>
     */
    public function getLeaderboard(GameRoom $room): array
    {
        return $room->players()
            ->orderByDesc('score')
            ->get()
            ->map(fn (GamePlayer $player) => [
                'player_id' => $player->id,
                'nickname' => $player->nickname,
                'score' => $player->score,
                'correct_answers_count' => $player->correct_answers_count,
                'is_host' => $player->is_host,
            ])
            ->values()
            ->all();
    }

    /**
     * Reset a finished room so it can be started again.
     * Resets player scores, removes old game session data, and sets status back to waiting.
     */
    private function resetRoom(GameRoom $room): void
    {
        DB::transaction(function () use ($room): void {
            // Reset all player scores and stats
            $room->players()->update([
                'score' => 0,
                'correct_answers_count' => 0,
                'is_connected' => true,
            ]);

            // Reset room state
            $room->update([
                'status' => RoomStatus::WaitingForPlayers,
                'game_session_id' => null,
                'current_round' => 0,
                'started_at' => null,
                'finished_at' => null,
            ]);

            $room->refresh();
        });
    }

    /**
     * Generate a unique 6-character room code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (GameRoom::where('code', $code)->exists());

        return $code;
    }

    /**
     * Fetch a fresh preview URL for a track from the Deezer API.
     */
    private function fetchPreviewUrl(Track $track): ?string
    {
        $trackData = $this->deezerApi->getTrack($track->deezer_id);

        return $trackData['preview'] ?? null;
    }

    /**
     * Evaluate whether the answer is correct.
     */
    private function evaluateAnswer(GameRound $round, ?int $guessedTrackId, ?string $textGuess): bool
    {
        if ($guessedTrackId !== null) {
            return $round->track_id === $guessedTrackId;
        }

        if ($textGuess !== null) {
            $correctTitle = mb_strtolower(trim($round->track->title));
            $guess = mb_strtolower(trim($textGuess));

            return $correctTitle === $guess || str_contains($correctTitle, $guess) || str_contains($guess, $correctTitle);
        }

        return false;
    }

    /**
     * Resolve a player from the X-Player-Token header.
     */
    public function resolvePlayerFromToken(GameRoom $room, string $token): ?GamePlayer
    {
        return $room->players()->where('token', $token)->first();
    }
}
