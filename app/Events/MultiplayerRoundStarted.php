<?php

namespace App\Events;

use App\Models\GameRoom;
use App\Models\GameRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class MultiplayerRoundStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use InteractsWithSockets;

    public string $roomCode;

    public int $roundNumber;

    public int $totalRounds;

    public ?string $previewUrl;

    public int $snippetStartSecond;

    public int $snippetEndSecond;

    public string $difficulty;

    /** @var array<int, array{id: int, title: string}>|null */
    public ?array $trackOptions;

    public ?string $startedAt;

    public function __construct(GameRoom $gameRoom, GameRound $round)
    {
        $this->roomCode = $gameRoom->code;
        $this->roundNumber = $round->round_number;
        $this->totalRounds = $gameRoom->total_rounds;
        $this->previewUrl = $round->preview_url;
        $this->snippetStartSecond = $round->snippet_start_second;
        $this->snippetEndSecond = $round->snippet_end_second;
        $this->difficulty = $gameRoom->difficulty->value;
        $this->trackOptions = $round->track_options;
        $this->startedAt = $round->started_at?->toIso8601String();
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
            'total_rounds' => $this->totalRounds,
            'preview_url' => $this->previewUrl,
            'snippet_start_second' => $this->snippetStartSecond,
            'snippet_end_second' => $this->snippetEndSecond,
            'difficulty' => $this->difficulty,
            'track_options' => $this->trackOptions,
            'started_at' => $this->startedAt,
        ];
    }
}
