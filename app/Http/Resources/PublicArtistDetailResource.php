<?php

namespace App\Http\Resources;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Artist
 */
class PublicArtistDetailResource extends JsonResource
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
            'name' => $this->name,
            'cover' => $this->cover,
            'albums_count' => $this->albums_count,
            'fans' => $this->fans,
            'albums' => PublicAlbumResource::collection($this->whenLoaded('albums')),
            'playable_tracks_count' => $this->when(
                $this->playable_tracks_count !== null,
                $this->playable_tracks_count,
            ),
        ];
    }
}
