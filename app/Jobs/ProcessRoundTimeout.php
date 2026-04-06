<?php

namespace App\Jobs;

use App\Models\GamePlayerAnswer;
use App\Models\GameRoom;
use App\Services\MultiplayerGameService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRoundTimeout implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GameRoom $room,
        public int $roundNumber,
    ) {}

    /**
     * Execute the job.
     *
     * Auto-submits blank answers for any connected players who
     * haven't answered the specified round, then triggers
     * round completion check.
     */
    public function handle(MultiplayerGameService $multiplayerService): void
    {
        $this->room->refresh();

        if (! $this->room->isInProgress() || ! $this->room->gameSession) {
            return;
        }

        // Idempotency: skip if room already moved past this round
        if ($this->room->current_round !== $this->roundNumber) {
            return;
        }

        $currentRound = $this->room->gameSession->rounds()
            ->where('round_number', $this->roundNumber)
            ->first();

        if (! $currentRound || $currentRound->is_completed) {
            return;
        }

        $connectedPlayerIds = $this->room->players()
            ->where('is_connected', true)
            ->pluck('id');

        $answeredPlayerIds = GamePlayerAnswer::where('game_round_id', $currentRound->id)
            ->whereIn('game_player_id', $connectedPlayerIds)
            ->pluck('game_player_id');

        $missingPlayerIds = $connectedPlayerIds->diff($answeredPlayerIds);

        $timeLimitMs = $this->room->difficulty->answerTimeLimitSeconds() * 1000;

        foreach ($missingPlayerIds as $playerId) {
            GamePlayerAnswer::create([
                'game_player_id' => $playerId,
                'game_round_id' => $currentRound->id,
                'guessed_track_id' => null,
                'text_guess' => null,
                'answer_time_ms' => $timeLimitMs,
                'is_correct' => false,
                'points_awarded' => 0,
            ]);
        }

        // Trigger round completion check (broadcasts results + dispatches AdvanceToNextRound)
        $multiplayerService->checkRoundCompletion($this->room, $currentRound);
    }
}
