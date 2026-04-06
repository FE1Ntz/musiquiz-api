<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Track;

class TrackDeduplicationService
{
    /**
     * Find an existing track or create a new one, deduplicating by ISRC first, then deezer_id.
     *
     * @param  array{id: int, title: string, duration: int, track_position: int, explicit_lyrics: bool, isrc?: string|null}  $trackData
     */
    public function findOrCreate(array $trackData, Album $album): Track
    {
        $isrc = $trackData['isrc'] ?? null;
        $deezerId = $trackData['id'];

        /** @var Track|null $existingTrack */
        $existingTrack = null;

        if (! empty($isrc)) {
            $existingTrack = Track::where('isrc', $isrc)->first();
        }

        if (! $existingTrack) {
            $existingTrack = Track::where('deezer_id', $deezerId)->first();
        }

        if ($existingTrack) {
            $existingTrack->update([
                'title' => $trackData['title'],
                'duration' => $trackData['duration'],
                'track_position' => $trackData['track_position'] ?? null,
                'explicit_lyrics' => $trackData['explicit_lyrics'] ?? false,
            ]);

            return $existingTrack;
        }

        return Track::create([
            'deezer_id' => $deezerId,
            'title' => $trackData['title'],
            'duration' => $trackData['duration'],
            'track_position' => $trackData['track_position'] ?? null,
            'explicit_lyrics' => $trackData['explicit_lyrics'] ?? false,
            'isrc' => $isrc,
            'album_id' => $album->id,
        ]);
    }
}
