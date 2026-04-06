<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class GameSessionFinished implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public GameSession $gameSession) {}

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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->gameSession->id,
            'final_score' => $this->gameSession->score,
            'correct_answers_count' => $this->gameSession->correct_answers_count,
            'total_rounds' => $this->gameSession->total_rounds,
            'status' => $this->gameSession->status->value,
            'ended_at' => $this->gameSession->ended_at?->toIso8601String(),
        ];
    }
}
