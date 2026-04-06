<?php

namespace App\Events;

use App\Models\GameRound;
use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class GameRoundStarted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public GameSession $gameSession,
        public GameRound $round,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game-session.'.$this->gameSession->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * Never sends the correct track title or answer.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->gameSession->id,
            'round_number' => $this->round->round_number,
            'total_rounds' => $this->gameSession->total_rounds,
            'preview_url' => $this->round->preview_url,
            'snippet_start_second' => $this->round->snippet_start_second,
            'snippet_end_second' => $this->round->snippet_end_second,
            'difficulty' => $this->gameSession->difficulty->value,
            'started_at' => $this->round->started_at?->toIso8601String(),
        ];
    }
}
