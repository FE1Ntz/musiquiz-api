<?php

namespace App\Events;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PlayerLeftRoom implements ShouldBroadcast
{
    use InteractsWithSockets;

    public string $roomCode;

    /** @var array{id: int, nickname: string} */
    public array $playerData;

    public int $playerCount;

    public function __construct(GameRoom $gameRoom, GamePlayer $player)
    {
        $this->roomCode = $gameRoom->code;
        $this->playerData = [
            'id' => $player->id,
            'nickname' => $player->nickname,
        ];
        $this->playerCount = $gameRoom->players()->count();
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
            'player' => $this->playerData,
            'player_count' => $this->playerCount,
        ];
    }
}
