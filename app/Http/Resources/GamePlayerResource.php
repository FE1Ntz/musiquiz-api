<?php

namespace App\Http\Resources;

use App\Models\GamePlayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GamePlayer
 */
class GamePlayerResource extends JsonResource
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
            'nickname' => $this->nickname,
            'score' => $this->score,
            'correct_answers_count' => $this->correct_answers_count,
            'is_host' => $this->is_host,
            'is_connected' => $this->is_connected,
        ];
    }
}
