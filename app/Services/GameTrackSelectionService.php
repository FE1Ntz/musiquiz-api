<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Track;
use Illuminate\Support\Collection;

class GameTrackSelectionService
{
    /**
     * Select random tracks for a game session from an artist's catalog.
     *
     * @return Collection<int, Track>
     */
    public function selectTracksForGame(Artist $artist, int $numberOfTracks): Collection
    {
        return $artist->tracks()
            ->where('duration', '>', 0)
            ->inRandomOrder()
            ->limit($numberOfTracks)
            ->get();
    }

    /**
     * Get the number of playable tracks available for an artist.
     */
    public function countAvailableTracks(Artist $artist): int
    {
        return $artist->tracks()
            ->where('duration', '>', 0)
            ->count();
    }
}
