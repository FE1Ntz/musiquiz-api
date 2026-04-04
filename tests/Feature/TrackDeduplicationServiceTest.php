<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Track;
use App\Services\TrackDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TrackDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TrackDeduplicationService::class);
    }

    public function test_creates_new_track_when_no_duplicate_exists(): void
    {
        $album = Album::factory()->create();

        $trackData = [
            'id' => 1001,
            'title' => 'New Track',
            'duration' => 240,
            'track_position' => 1,
            'explicit_lyrics' => false,
            'isrc' => 'GBDUW0000059',
            'preview' => 'https://example.com/preview.mp3',
        ];

        $track = $this->service->findOrCreate($trackData, $album);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertEquals('New Track', $track->title);
        $this->assertEquals(1001, $track->deezer_id);
        $this->assertEquals('GBDUW0000059', $track->isrc);
    }

    public function test_deduplicates_by_isrc(): void
    {
        $album = Album::factory()->create();
        $existingTrack = Track::factory()->create([
            'isrc' => 'GBDUW0000059',
            'deezer_id' => 999,
            'album_id' => $album->id,
        ]);

        $trackData = [
            'id' => 1001,
            'title' => 'Same Track Different ID',
            'duration' => 240,
            'track_position' => 2,
            'explicit_lyrics' => false,
            'isrc' => 'GBDUW0000059',
            'preview' => 'https://example.com/preview.mp3',
        ];

        $track = $this->service->findOrCreate($trackData, $album);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertEquals($existingTrack->id, $track->id);
    }

    public function test_deduplicates_by_deezer_id_when_isrc_is_null(): void
    {
        $album = Album::factory()->create();
        $existingTrack = Track::factory()->withoutIsrc()->create([
            'deezer_id' => 1001,
            'album_id' => $album->id,
        ]);

        $trackData = [
            'id' => 1001,
            'title' => 'Updated Title',
            'duration' => 300,
            'track_position' => 1,
            'explicit_lyrics' => true,
            'isrc' => null,
            'preview' => 'https://example.com/new-preview.mp3',
        ];

        $track = $this->service->findOrCreate($trackData, $album);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertEquals($existingTrack->id, $track->id);
        $this->assertEquals('Updated Title', $track->fresh()->title);
    }

    public function test_creates_separate_tracks_with_different_isrc(): void
    {
        $album = Album::factory()->create();

        Track::factory()->create([
            'isrc' => 'GBDUW0000059',
            'album_id' => $album->id,
        ]);

        $trackData = [
            'id' => 2002,
            'title' => 'Different Track',
            'duration' => 180,
            'track_position' => 2,
            'explicit_lyrics' => false,
            'isrc' => 'GBDUW0000060',
            'preview' => 'https://example.com/preview2.mp3',
        ];

        $this->service->findOrCreate($trackData, $album);

        $this->assertDatabaseCount('tracks', 2);
    }

    public function test_creates_track_with_null_isrc_when_no_deezer_id_match(): void
    {
        $album = Album::factory()->create();

        $trackData = [
            'id' => 5001,
            'title' => 'No ISRC Track',
            'duration' => 200,
            'track_position' => 1,
            'explicit_lyrics' => false,
            'isrc' => null,
            'preview' => 'https://example.com/preview.mp3',
        ];

        $track = $this->service->findOrCreate($trackData, $album);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertNull($track->isrc);
        $this->assertEquals(5001, $track->deezer_id);
    }
}
