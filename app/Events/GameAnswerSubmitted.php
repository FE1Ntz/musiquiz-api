<?php

namespace App\Events;

use App\Models\GameAnswer;
use App\Models\GameRound;
use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class GameAnswerSubmitted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public GameSession $gameSession,
        public GameRound $round,
        public GameAnswer $answer,
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
     * Never exposes the correct answer.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'game_session_id' => $this->gameSession->id,
            'round_number' => $this->round->round_number,
            'is_correct' => $this->answer->is_correct,
            'points_awarded' => $this->answer->points_awarded,
            'round_finished' => $this->round->is_completed,
        ];
    }
}
