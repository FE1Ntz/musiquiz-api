<?php

namespace App\Services;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\GameStatus;
use App\Events\GameAnswerSubmitted;
use App\Events\GameRoundStarted;
use App\Events\GameScoreUpdated;
use App\Events\GameSessionCreated;
use App\Events\GameSessionFinished;
use App\Models\Artist;
use App\Models\GameAnswer;
use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameSessionService
{
    private const int DEFAULT_TOTAL_ROUNDS = 10;

    public function __construct(
        private GameTrackSelectionService $trackSelectionService,
        private SnippetTimingService $snippetTimingService,
        private GameScoringService $scoringService,
        private DeezerApiService $deezerApi,
    ) {}

    /**
     * Create a new single-player game session.
     *
     * @throws ValidationException
     */
    public function createSinglePlayerSession(
        Artist $artist,
        Difficulty $difficulty,
        AnswerMode $answerMode = AnswerMode::MultipleChoice,
        ?User $user = null,
        ?string $guestSessionId = null,
    ): GameSession {
        $availableTrackCount = $this->trackSelectionService->countAvailableTracks($artist);

        if ($availableTrackCount === 0) {
            throw ValidationException::withMessages([
                'artist_id' => ['This artist has no playable tracks.'],
            ]);
        }

        $totalRounds = min(self::DEFAULT_TOTAL_ROUNDS, $availableTrackCount);

        return DB::transaction(function () use ($artist, $difficulty, $answerMode, $user, $guestSessionId, $totalRounds): GameSession {
            $gameSession = GameSession::create([
                'user_id' => $user?->id,
                'guest_session_id' => $guestSessionId,
                'artist_id' => $artist->id,
                'difficulty' => $difficulty,
                'answer_mode' => $answerMode,
                'current_round' => 0,
                'total_rounds' => $totalRounds,
                'score' => 0,
                'status' => GameStatus::Waiting,
            ]);

            $tracks = $this->trackSelectionService->selectTracksForGame($artist, $totalRounds);

            foreach ($tracks as $index => $track) {
                $roundNumber = $index + 1;
                $timing = $this->snippetTimingService->calculateSnippetTiming(
                    $difficulty,
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

            $gameSession->load('rounds');

            event(new GameSessionCreated($gameSession));

            return $gameSession;
        });
    }

    /**
     * Start the next round of the game session.
     *
     * @throws ValidationException
     */
    public function startNextRound(GameSession $gameSession): GameRound
    {
        if ($gameSession->isFinished()) {
            throw ValidationException::withMessages([
                'game_session' => ['This game session is already finished.'],
            ]);
        }

        $nextRoundNumber = $gameSession->current_round + 1;

        if ($nextRoundNumber > $gameSession->total_rounds) {
            return $this->finishGame($gameSession) ? $gameSession->currentRound() : throw ValidationException::withMessages([
                'game_session' => ['No more rounds available.'],
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

        $gameSession->update([
            'current_round' => $nextRoundNumber,
            'status' => GameStatus::InProgress,
            'started_at' => $gameSession->started_at ?? now(),
        ]);

        $gameSession->refresh();

        event(new GameRoundStarted($gameSession, $round));

        return $round;
    }

    /**
     * Submit an answer for the current round.
     *
     * @return array{answer: GameAnswer, is_correct: bool, points_awarded: int, updated_total_score: int}
     *
     * @throws ValidationException
     */
    public function submitAnswer(
        GameSession $gameSession,
        ?int $guessedTrackId,
        ?string $textGuess,
        int $answerTimeMs,
    ): array {
        if (! $gameSession->isInProgress()) {
            throw ValidationException::withMessages([
                'game_session' => ['This game session is not in progress.'],
            ]);
        }

        $currentRound = $gameSession->currentRound();

        if (! $currentRound || $currentRound->is_completed) {
            throw ValidationException::withMessages([
                'round' => ['No active round to answer.'],
            ]);
        }

        // Check if already answered
        $existingAnswer = GameAnswer::where('game_round_id', $currentRound->id)
            ->where('game_session_id', $gameSession->id)
            ->first();

        if ($existingAnswer) {
            throw ValidationException::withMessages([
                'round' => ['This round has already been answered.'],
            ]);
        }

        // Reject answers submitted after the time limit (with 2s grace period for network latency)
        $timeLimitSeconds = $gameSession->difficulty->answerTimeLimitSeconds();
        $deadline = $currentRound->started_at->addSeconds($timeLimitSeconds + 2);

        if (now()->greaterThan($deadline)) {
            throw ValidationException::withMessages([
                'round' => ['The time limit for this round has expired.'],
            ]);
        }

        $isCorrect = $this->evaluateAnswer($currentRound, $guessedTrackId, $textGuess);

        $pointsAwarded = $isCorrect
            ? $this->scoringService->calculateCorrectAnswerPoints($gameSession->difficulty, $answerTimeMs)
            : $this->scoringService->calculateWrongAnswerPenalty();

        $updatedScore = $this->scoringService->applyPoints($gameSession->score, $pointsAwarded);

        return DB::transaction(function () use (
            $gameSession,
            $currentRound,
            $guessedTrackId,
            $textGuess,
            $answerTimeMs,
            $isCorrect,
            $pointsAwarded,
            $updatedScore,
        ): array {
            $answer = GameAnswer::create([
                'game_round_id' => $currentRound->id,
                'game_session_id' => $gameSession->id,
                'guessed_track_id' => $guessedTrackId,
                'text_guess' => $textGuess,
                'answer_time_ms' => $answerTimeMs,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
            ]);

            $currentRound->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

            $gameSession->update([
                'score' => $updatedScore,
                'correct_answers_count' => $gameSession->correct_answers_count + ($isCorrect ? 1 : 0),
            ]);
            $gameSession->refresh();

            event(new GameAnswerSubmitted($gameSession, $currentRound, $answer));
            event(new GameScoreUpdated($gameSession));

            return [
                'answer' => $answer,
                'is_correct' => $isCorrect,
                'points_awarded' => $pointsAwarded,
                'updated_total_score' => $updatedScore,
            ];
        });
    }

    /**
     * Skip the current round due to timeout. Awards 0 points.
     *
     * @return array{answer: GameAnswer, is_correct: bool, points_awarded: int, updated_total_score: int}
     *
     * @throws ValidationException
     */
    public function skipRound(GameSession $gameSession): array
    {
        if (! $gameSession->isInProgress()) {
            throw ValidationException::withMessages([
                'game_session' => ['This game session is not in progress.'],
            ]);
        }

        $currentRound = $gameSession->currentRound();

        if (! $currentRound || $currentRound->is_completed) {
            throw ValidationException::withMessages([
                'round' => ['No active round to skip.'],
            ]);
        }

        $existingAnswer = GameAnswer::where('game_round_id', $currentRound->id)
            ->where('game_session_id', $gameSession->id)
            ->first();

        if ($existingAnswer) {
            throw ValidationException::withMessages([
                'round' => ['This round has already been answered.'],
            ]);
        }

        return DB::transaction(function () use ($gameSession, $currentRound): array {
            $answer = GameAnswer::create([
                'game_round_id' => $currentRound->id,
                'game_session_id' => $gameSession->id,
                'guessed_track_id' => null,
                'text_guess' => null,
                'answer_time_ms' => $gameSession->difficulty->answerTimeLimitSeconds() * 1000,
                'is_correct' => false,
                'points_awarded' => 0,
            ]);

            $currentRound->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

            $gameSession->refresh();

            event(new GameAnswerSubmitted($gameSession, $currentRound, $answer));

            return [
                'answer' => $answer,
                'is_correct' => false,
                'points_awarded' => 0,
                'updated_total_score' => $gameSession->score,
            ];
        });
    }

    /**
     * Finish a game session.
     */
    public function finishGame(GameSession $gameSession): bool
    {
        if ($gameSession->isFinished()) {
            return false;
        }

        // Complete any unanswered rounds
        $gameSession->rounds()
            ->where('is_completed', false)
            ->update([
                'is_completed' => true,
                'completed_at' => now(),
            ]);

        $gameSession->update([
            'status' => GameStatus::Finished,
            'ended_at' => now(),
        ]);

        $gameSession->refresh();

        event(new GameSessionFinished($gameSession));

        return true;
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

            // Exact match or close enough (contains the title)
            return $correctTitle === $guess || str_contains($correctTitle, $guess) || str_contains($guess, $correctTitle);
        }

        return false;
    }

    /**
     * Get safe game state data for the frontend.
     *
     * @return array<string, mixed>
     */
    public function getGameState(GameSession $gameSession): array
    {
        $gameSession->load(['artist:id,name,cover', 'rounds']);

        $currentRound = $gameSession->currentRound();

        $state = [
            'id' => $gameSession->id,
            'artist' => [
                'id' => $gameSession->artist->id,
                'name' => $gameSession->artist->name,
                'cover' => $gameSession->artist->cover,
            ],
            'difficulty' => $gameSession->difficulty->value,
            'answer_mode' => $gameSession->answer_mode->value,
            'current_round' => $gameSession->current_round,
            'total_rounds' => $gameSession->total_rounds,
            'score' => $gameSession->score,
            'correct_answers_count' => $gameSession->correct_answers_count,
            'status' => $gameSession->status->value,
            'started_at' => $gameSession->started_at?->toIso8601String(),
            'ended_at' => $gameSession->ended_at?->toIso8601String(),
        ];

        if ($currentRound && ! $currentRound->is_completed) {
            $state['current_round_data'] = [
                'round_number' => $currentRound->round_number,
                'preview_url' => $currentRound->preview_url,
                'snippet_start_second' => $currentRound->snippet_start_second,
                'snippet_end_second' => $currentRound->snippet_end_second,
                'started_at' => $currentRound->started_at?->toIso8601String(),
            ];
        }

        return $state;
    }
}
