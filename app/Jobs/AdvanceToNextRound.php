<?php

namespace App\Jobs;

use App\Models\GameRoom;
use App\Services\MultiplayerGameService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AdvanceToNextRound implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public GameRoom $room,
        public int $completedRoundNumber,
    ) {}

    /**
     * Execute the job.
     *
     * Advances to the next round or finishes the game after
     * the results display delay has elapsed.
     */
    public function handle(MultiplayerGameService $multiplayerService): void
    {
        $this->room->refresh();

        // Idempotency: skip if room already moved past this round
        if ($this->room->current_round !== $this->completedRoundNumber) {
            return;
        }

        if (! $this->room->isInProgress()) {
            return;
        }

        // Last round → finish the game
        if ($this->room->current_round >= $this->room->total_rounds) {
            $multiplayerService->finishGame($this->room);

            return;
        }

        // Advance to the next round
        $multiplayerService->startNextRound($this->room);
    }
}
