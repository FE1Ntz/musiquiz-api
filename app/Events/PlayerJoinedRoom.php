<?php

namespace App\Events;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class PlayerJoinedRoom implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use InteractsWithSockets;

    public string $roomCode;

    /** @var array{id: int, nickname: string, is_host: bool} */
    public array $playerData;

    public int $playerCount;

    public int $maxPlayers;

    public function __construct(GameRoom $gameRoom, GamePlayer $player)
    {
        $this->roomCode = $gameRoom->code;
        $this->playerData = [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'is_host' => $player->is_host,
        ];
        $this->playerCount = $gameRoom->players()->count();
        $this->maxPlayers = $gameRoom->max_players;
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
            'max_players' => $this->maxPlayers,
        ];
    }
}
