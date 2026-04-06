<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ArtistImportService
{
    public function __construct(
        protected DeezerApiService $deezerApi,
        protected ArtistResolverService $artistResolver,
        protected TrackDeduplicationService $trackDeduplication,
    ) {}

    /**
     * Import the full catalog for an artist: albums, tracks, and collaborators.
     */
    public function importCatalog(Artist $artist): void
    {
        $albums = $this->deezerApi->getArtistAlbums($artist->deezer_id);

        Log::info('Starting catalog import', [
            'deezer_artist_id' => $artist->deezer_id,
            'artist_name' => $artist->name,
            'albums_found' => count($albums),
        ]);

        foreach ($albums as $albumData) {
            try {
                $this->importAlbum($albumData, $artist);
            } catch (Throwable $e) {
                Log::error('Failed to import album', [
                    'deezer_artist_id' => $artist->deezer_id,
                    'deezer_album_id' => $albumData['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Catalog import completed', [
            'deezer_artist_id' => $artist->deezer_id,
            'artist_name' => $artist->name,
        ]);
    }

    /**
     * Import a single album and its tracks.
     *
     * @param  array<string, mixed>  $albumData
     */
    protected function importAlbum(array $albumData, Artist $artist): void
    {
        DB::transaction(function () use ($albumData, $artist): void {
            $album = Album::updateOrCreate(
                ['deezer_id' => $albumData['id']],
                [
                    'title' => $albumData['title'],
                    'cover' => $albumData['cover_medium'] ?? $albumData['cover'] ?? null,
                    'record_type' => $albumData['record_type'] ?? null,
                    'release_date' => $albumData['release_date'] ?? null,
                    'explicit_lyrics' => $albumData['explicit_lyrics'] ?? false,
                ],
            );

            $album->artists()->syncWithoutDetaching([$artist->id]);

            $tracks = $this->deezerApi->getAlbumTracks($albumData['id']);

            foreach ($tracks as $trackData) {
                try {
                    $this->importTrack($trackData, $album);
                } catch (Throwable $e) {
                    Log::error('Failed to import track', [
                        'deezer_artist_id' => $artist->deezer_id,
                        'deezer_album_id' => $albumData['id'],
                        'deezer_track_id' => $trackData['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Import a single track and attach its artists.
     *
     * @param  array<string, mixed>  $trackData
     */
    protected function importTrack(array $trackData, Album $album): void
    {
        $track = $this->trackDeduplication->findOrCreate($trackData, $album);

        $artistIds = $this->resolveTrackArtists($trackData);
        $track->artists()->syncWithoutDetaching($artistIds);
    }

    /**
     * Resolve all artists associated with a track, creating any missing collaborators.
     *
     * @param  array<string, mixed>  $trackData
     * @return array<int, int>
     */
    protected function resolveTrackArtists(array $trackData): array
    {
        $artistIds = [];

        /** @var array<int, array{id: int, name: string}> $contributors */
        $contributors = $trackData['contributors'] ?? [];

        if (empty($contributors) && isset($trackData['artist'])) {
            $contributors = [$trackData['artist']];
        }

        foreach ($contributors as $contributorData) {
            $existingArtist = Artist::where('deezer_id', $contributorData['id'])->first();

            if ($existingArtist) {
                $artistIds[] = $existingArtist->id;

                continue;
            }

            $fullArtistData = $this->deezerApi->getArtist($contributorData['id']);
            $artist = $this->artistResolver->resolveFromDeezer($fullArtistData ?? $contributorData);
            $artistIds[] = $artist->id;
        }

        return $artistIds;
    }
}
