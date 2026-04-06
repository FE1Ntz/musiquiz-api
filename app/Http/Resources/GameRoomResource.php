<?php

namespace App\Http\Resources;

use App\Models\GameRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameRoom
 */
class GameRoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'artist' => new PublicArtistResource($this->whenLoaded('artist')),
            'difficulty' => $this->difficulty->value,
            'answer_mode' => $this->answer_mode->value,
            'status' => $this->status->value,
            'max_players' => $this->max_players,
            'current_round' => $this->current_round,
            'total_rounds' => $this->total_rounds,
            'players' => GamePlayerResource::collection($this->whenLoaded('players')),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
