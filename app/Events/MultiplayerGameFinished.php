<?php

namespace App\Events;

use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MultiplayerGameFinished implements ShouldBroadcast
{
    use InteractsWithSockets;

    public string $roomCode;

    public string $status;

    /** @var array<int, array{player_id: int, nickname: string, score: int, correct_answers_count: int}> */
    public array $leaderboard;

    public ?string $finishedAt;

    public function __construct(GameRoom $gameRoom)
    {
        $this->roomCode = $gameRoom->code;
        $this->status = $gameRoom->status->value;
        $this->finishedAt = $gameRoom->finished_at?->toIso8601String();
        $this->leaderboard = $gameRoom->players()
            ->orderByDesc('score')
            ->get()
            ->map(fn ($player) => [
                'player_id' => $player->id,
                'nickname' => $player->nickname,
                'score' => $player->score,
                'correct_answers_count' => $player->correct_answers_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('game-room.'.$this->roomCode)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_code' => $this->roomCode,
            'status' => $this->status,
            'leaderboard' => $this->leaderboard,
            'finished_at' => $this->finishedAt,
        ];
    }
}
