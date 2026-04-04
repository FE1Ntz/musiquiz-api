<?php

namespace App\Services;

use App\Models\Artist;

class ArtistResolverService
{
    /**
     * Find or create an artist from Deezer API data.
     *
     * @param  array{id: int, name: string, picture_medium?: string, picture?: string, nb_album?: int, nb_fan?: int}  $deezerArtistData
     */
    public function resolveFromDeezer(array $deezerArtistData): Artist
    {
        return Artist::updateOrCreate(
            ['deezer_id' => $deezerArtistData['id']],
            [
                'name' => $deezerArtistData['name'],
                'cover' => $deezerArtistData['picture_medium']
                    ?? $deezerArtistData['picture']
                    ?? "https://api.deezer.com/artist/{$deezerArtistData['id']}/image",
                'albums_count' => $deezerArtistData['nb_album'] ?? 0,
                'fans' => $deezerArtistData['nb_fan'] ?? 0,
            ],
        );
    }
}
