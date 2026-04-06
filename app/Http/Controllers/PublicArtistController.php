<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicArtistDetailResource;
use App\Http\Resources\PublicArtistResource;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicArtistController extends Controller
{
    /**
     * Display a paginated list of artists.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:name,fans,albums_count'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Artist::query()
            ->whereHas('tracks', function ($q): void {
                $q->where('duration', '>', 0);
            }, '>', 10);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        $sortField = $request->input('sort', 'fans');
        $sortDirection = $request->input('direction', 'desc');

        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->input('per_page', 15);

        return PublicArtistResource::collection($query->paginate($perPage));
    }

    /**
     * Display a single artist with albums and stats.
     */
    public function show(Artist $artist): PublicArtistDetailResource
    {
        $artist->load('albums');
        $artist->playable_tracks_count = $artist->tracks()
            ->where('duration', '>', 0)
            ->count();

        return new PublicArtistDetailResource($artist);
    }
}
