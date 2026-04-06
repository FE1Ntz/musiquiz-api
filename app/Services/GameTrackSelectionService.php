<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\GameRound;
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

    /**
     * Build (or retrieve cached) track options for a multiple choice round.
     *
     * Returns the correct answer plus 3 random decoy tracks from the same artist,
     * shuffled into a random order. Options are generated once and persisted on
     * the round so every player receives the same set.
     *
     * @return array<int, array{id: int, title: string}>
     */
    public function buildTrackOptions(Artist $artist, GameRound $round): array
    {
        if (! empty($round->track_options)) {
            return $round->track_options;
        }

        $correctTrack = $round->track;

        $decoys = $artist->tracks()
            ->where('tracks.id', '!=', $correctTrack->id)
            ->where('duration', '>', 0)
            ->inRandomOrder()
            ->limit(3)
            ->get(['tracks.id', 'title']);

        $options = $decoys->push($correctTrack)
            ->shuffle()
            ->map(fn (Track $track): array => [
                'id' => $track->id,
                'title' => $track->title,
            ])
            ->values()
            ->all();

        $round->update(['track_options' => $options]);

        return $options;
    }
}
