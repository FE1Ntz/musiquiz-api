<?php

namespace App\Http\Resources;

use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Album
 */
class PublicAlbumResource extends JsonResource
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
            'deezer_id' => $this->deezer_id,
            'title' => $this->title,
            'cover' => $this->cover,
            'record_type' => $this->record_type,
            'release_date' => $this->release_date?->toDateString(),
        ];
    }
}
