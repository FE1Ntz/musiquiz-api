<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchArtistRequest;
use App\Services\DeezerApiService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Deezer Artists', 'Пошук та перегляд артистів через Deezer API.', weight: 1)]
class DeezerArtistController extends Controller
{
    public function __construct(
        protected DeezerApiService $deezerApi,
    ) {}

    /**
     * Search Deezer Artists
     *
     * Search for artists on Deezer by name.
     *
     * @operationId searchDeezerArtists
     *
     * @response 200 {
     *   "data": [{"id": 27, "name": "Daft Punk", "picture_medium": "https://example.com/img.jpg"}],
     *   "total": 1
     * }
     */
    public function search(SearchArtistRequest $request): JsonResponse
    {
        $results = $this->deezerApi->searchArtists(
            $request->validated('query'),
        );

        return response()->json($results);
    }

    /**
     * Get Deezer Artist
     *
     * Retrieve a single artist from Deezer by their ID.
     *
     * @operationId showDeezerArtist
     *
     * @response 200 {
     *   "data": {"id": 27, "name": "Daft Punk", "picture_medium": "https://example.com/img.jpg", "nb_album": 12, "nb_fan": 5000000}
     * }
     * @response 404 {"message": "Artist not found on Deezer."}
     */
    public function show(int $deezerArtistId): JsonResponse
    {
        $artist = $this->deezerApi->getArtist($deezerArtistId);

        if (! $artist) {
            return response()->json(['message' => 'Artist not found on Deezer.'], 404);
        }

        return response()->json(['data' => $artist]);
    }
}
