<?php

namespace App\Http\Resources;

use App\Models\GameRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameRound
 */
class GameRoundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Only exposes safe gameplay data — never the correct track title.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'preview_url' => $this->preview_url,
            'snippet_start_second' => $this->snippet_start_second,
            'snippet_end_second' => $this->snippet_end_second,
            'is_completed' => $this->is_completed,
            'started_at' => $this->started_at?->toIso8601String(),
        ];
    }
}
