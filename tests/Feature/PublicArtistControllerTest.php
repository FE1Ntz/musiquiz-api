<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicArtistControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an artist with enough playable tracks to appear in the index.
     */
    private function createArtistWithTracks(array $attributes = [], int $trackCount = 11): Artist
    {
        $artist = Artist::factory()->create($attributes);
        $tracks = Track::factory()->count($trackCount)->create(['duration' => 30]);
        $artist->tracks()->attach($tracks->pluck('id'));

        return $artist;
    }

    public function test_index_returns_paginated_artists(): void
    {
        $this->createArtistWithTracks();
        $this->createArtistWithTracks();
        $this->createArtistWithTracks();

        $this->getJson(route('artists.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'deezer_id', 'name', 'cover', 'albums_count', 'fans'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_filters_by_search(): void
    {
        $this->createArtistWithTracks(['name' => 'Eminem']);
        $this->createArtistWithTracks(['name' => 'Drake']);
        $this->createArtistWithTracks(['name' => 'Emilia']);

        $this->getJson(route('artists.index', ['search' => 'Emi']))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_sorts_by_name_ascending(): void
    {
        $this->createArtistWithTracks(['name' => 'Zedd']);
        $this->createArtistWithTracks(['name' => 'Adele']);
        $this->createArtistWithTracks(['name' => 'Moby']);

        $response = $this->getJson(route('artists.index', [
            'sort' => 'name',
            'direction' => 'asc',
        ]))
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Adele', 'Moby', 'Zedd'], $names);
    }

    public function test_index_sorts_by_fans_descending_by_default(): void
    {
        $this->createArtistWithTracks(['fans' => 100]);
        $this->createArtistWithTracks(['fans' => 500]);
        $this->createArtistWithTracks(['fans' => 300]);

        $response = $this->getJson(route('artists.index'))
            ->assertOk();

        $fans = collect($response->json('data'))->pluck('fans')->toArray();
        $this->assertEquals([500, 300, 100], $fans);
    }

    public function test_index_respects_per_page(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createArtistWithTracks();
        }

        $this->getJson(route('artists.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_validates_sort_parameter(): void
    {
        $this->getJson(route('artists.index', ['sort' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_index_returns_empty_when_no_artists(): void
    {
        $this->getJson(route('artists.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_excludes_artists_with_10_or_fewer_tracks(): void
    {
        $this->createArtistWithTracks(['name' => 'Popular'], 15);
        $this->createArtistWithTracks(['name' => 'Small'], 5);
        $this->createArtistWithTracks(['name' => 'Exact'], 10);

        $response = $this->getJson(route('artists.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Popular', $response->json('data.0.name'));
    }

    public function test_index_does_not_expose_sensitive_fields(): void
    {
        $this->createArtistWithTracks();

        $response = $this->getJson(route('artists.index'))
            ->assertOk();

        $artist = $response->json('data.0');
        $this->assertArrayNotHasKey('created_at', $artist);
        $this->assertArrayNotHasKey('updated_at', $artist);
    }

    public function test_show_returns_artist_with_albums(): void
    {
        $artist = Artist::factory()->create();
        $album = Album::factory()->create();
        $artist->albums()->attach($album);

        $track = Track::factory()->create();
        $artist->tracks()->attach($track);

        $this->getJson(route('artists.show', $artist))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'deezer_id', 'name', 'cover', 'albums_count', 'fans',
                    'albums' => [
                        '*' => ['id', 'deezer_id', 'title', 'cover', 'record_type', 'release_date'],
                    ],
                    'playable_tracks_count',
                ],
            ])
            ->assertJsonPath('data.playable_tracks_count', 1);
    }

    public function test_show_returns_404_for_nonexistent_artist(): void
    {
        $this->getJson(route('artists.show', 99999))
            ->assertNotFound();
    }
}
