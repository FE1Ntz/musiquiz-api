<?php

namespace App\Http\Resources;

use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameSession
 */
class GameSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Never exposes correct answers or track titles.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'artist' => new PublicArtistResource($this->whenLoaded('artist')),
            'difficulty' => $this->difficulty->value,
            'answer_mode' => $this->answer_mode->value,
            'current_round' => $this->current_round,
            'total_rounds' => $this->total_rounds,
            'score' => $this->score,
            'correct_answers_count' => $this->correct_answers_count,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
