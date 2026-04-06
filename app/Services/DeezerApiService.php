<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeezerApiService
{
    /**
     * Create a configured HTTP client for Deezer API requests.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('services.deezer.base_url'))
            ->timeout(15)
            ->retry(3, 500, throw: false);
    }

    /**
     * Search for artists by name.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     */
    public function searchArtists(string $query, int $limit = 25): array
    {
        $response = $this->client()->get('/search/artist', [
            'q' => $query,
            'limit' => $limit,
        ]);

        if ($response->failed()) {
            Log::error('Deezer API: Failed to search artists', [
                'query' => $query,
                'status' => $response->status(),
            ]);

            return ['data' => [], 'total' => 0];
        }

        return $response->json();
    }

    /**
     * Get a single artist by Deezer ID.
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getArtist(int $deezerId): ?array
    {
        $response = $this->client()->get("/artist/{$deezerId}");

        if ($response->failed() || isset($response->json()['error'])) {
            Log::error('Deezer API: Failed to get artist', [
                'deezer_artist_id' => $deezerId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Get a single track by Deezer ID (includes a fresh preview URL).
     *
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getTrack(int $deezerId): ?array
    {
        $response = $this->client()->get("/track/{$deezerId}");

        if ($response->failed() || isset($response->json()['error'])) {
            Log::error('Deezer API: Failed to get track', [
                'deezer_track_id' => $deezerId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /**
     * Get all albums for an artist, handling pagination.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getArtistAlbums(int $deezerId): array
    {
        $albums = [];
        $index = 0;
        $limit = 50;

        do {
            $response = $this->client()->get("/artist/{$deezerId}/albums", [
                'index' => $index,
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                Log::error('Deezer API: Failed to fetch artist albums', [
                    'deezer_artist_id' => $deezerId,
                    'index' => $index,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data = $response->json();
            $albums = array_merge($albums, $data['data'] ?? []);
            $index += $limit;
        } while (isset($data['next']));

        return $albums;
    }

    /**
     * Get all tracks for an album.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getAlbumTracks(int $albumDeezerId): array
    {
        $tracks = [];
        $index = 0;
        $limit = 50;

        do {
            $response = $this->client()->get("/album/{$albumDeezerId}/tracks", [
                'index' => $index,
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                Log::error('Deezer API: Failed to fetch album tracks', [
                    'deezer_album_id' => $albumDeezerId,
                    'index' => $index,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data = $response->json();
            $tracks = array_merge($tracks, $data['data'] ?? []);
            $index += $limit;
        } while (isset($data['next']));

        return $tracks;
    }
}
