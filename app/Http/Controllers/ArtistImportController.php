<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportArtistRequest;
use App\Jobs\ImportArtistCatalogJob;
use App\Services\ArtistResolverService;
use App\Services\DeezerApiService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Artist Import', 'Імпорт артистів із Deezer у локальну базу.', weight: 2)]
class ArtistImportController extends Controller
{
    public function __construct(
        protected DeezerApiService $deezerApi,
        protected ArtistResolverService $artistResolver,
    ) {}

    /**
     * Import Artist
     *
     * Import a Deezer artist by ID and dispatch a background job to import their full catalog (albums & tracks).
     *
     * @operationId importArtist
     *
     * @response 202 {
     *   "message": "Artist imported. Catalog import has been queued.",
     *   "data": {"id": 1, "name": "Daft Punk", "deezer_id": 27, "created_at": "2026-04-04T17:00:00.000000Z", "updated_at": "2026-04-04T17:00:00.000000Z"}
     * }
     * @response 422 {"message": "Artist not found on Deezer."}
     */
    public function store(ImportArtistRequest $request): JsonResponse
    {
        $deezerId = $request->validated('deezer_id');

        $deezerArtistData = $this->deezerApi->getArtist($deezerId);

        if (! $deezerArtistData) {
            return response()->json(['message' => 'Artist not found on Deezer.'], 422);
        }

        $artist = $this->artistResolver->resolveFromDeezer($deezerArtistData);

        ImportArtistCatalogJob::dispatch($artist);

        return response()->json([
            'message' => 'Artist imported. Catalog import has been queued.',
            'data' => $artist,
        ], 202);
    }
}
