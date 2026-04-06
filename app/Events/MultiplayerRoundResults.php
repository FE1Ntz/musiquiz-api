<?php

namespace App\Events;

use App\Models\GameRoom;
use App\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class MultiplayerRoundResults implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use InteractsWithSockets;

    public string $roomCode;

    public int $roundNumber;

    /** @var array{id: int, title: string} */
    public array $correctTrack;

    /** @var array<int, array{player_id: int, nickname: string, is_correct: bool, points_awarded: int, answer_time_ms: int, total_score: int}> */
    public array $playerResults;

    /**
     * @param  array<int, array{player_id: int, nickname: string, is_correct: bool, points_awarded: int, answer_time_ms: int, total_score: int}>  $playerResults
     */
    public function __construct(GameRoom $gameRoom, GameRound $round, array $playerResults)
    {
        $this->roomCode = $gameRoom->code;
        $this->roundNumber = $round->round_number;
        $this->correctTrack = [
            'id' => $round->track_id,
            'title' => $round->track->title,
        ];
        $this->playerResults = $playerResults;
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
            'round_number' => $this->roundNumber,
            'correct_track' => $this->correctTrack,
            'player_results' => $this->playerResults,
        ];
    }
}
