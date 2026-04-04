<?php

namespace Tests\Feature;

use App\Jobs\ImportArtistCatalogJob;
use App\Models\Artist;
use App\Services\DeezerApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ImportArtistCatalogJobTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDeezerTrack(int $id, string $title, string $isrc, array $contributors = []): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'duration' => 240,
            'track_position' => 1,
            'explicit_lyrics' => false,
            'isrc' => $isrc,
            'preview' => "https://example.com/preview/{$id}.mp3",
            'contributors' => $contributors,
            'artist' => $contributors[0] ?? ['id' => 27, 'name' => 'Daft Punk'],
        ];
    }

    public function test_job_imports_albums_and_tracks(): void
    {
        $artist = Artist::factory()->create(['deezer_id' => 27, 'name' => 'Daft Punk']);

        $albumData = [
            [
                'id' => 301,
                'title' => 'Discovery',
                'record_type' => 'album',
                'release_date' => '2001-03-12',
                'explicit_lyrics' => false,
            ],
        ];

        $trackData = [
            $this->makeDeezerTrack(1001, 'One More Time', 'GBDUW0000059', [
                ['id' => 27, 'name' => 'Daft Punk'],
            ]),
            $this->makeDeezerTrack(1002, 'Aerodynamic', 'GBDUW0000060', [
                ['id' => 27, 'name' => 'Daft Punk'],
            ]),
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($albumData, $trackData): void {
            $mock->shouldReceive('getArtistAlbums')
                ->with(27)
                ->once()
                ->andReturn($albumData);

            $mock->shouldReceive('getAlbumTracks')
                ->with(301)
                ->once()
                ->andReturn($trackData);
        });

        ImportArtistCatalogJob::dispatchSync($artist);

        $this->assertDatabaseHas('albums', ['deezer_id' => 301, 'title' => 'Discovery']);
        $this->assertDatabaseHas('tracks', ['deezer_id' => 1001, 'title' => 'One More Time']);
        $this->assertDatabaseHas('tracks', ['deezer_id' => 1002, 'title' => 'Aerodynamic']);
        $this->assertDatabaseCount('tracks', 2);
        $this->assertDatabaseHas('album_artist', ['artist_id' => $artist->id]);
        $this->assertDatabaseCount('artist_track', 2);
    }

    public function test_job_creates_collaborator_artists(): void
    {
        $artist = Artist::factory()->create(['deezer_id' => 27, 'name' => 'Daft Punk']);

        $albumData = [
            [
                'id' => 302,
                'title' => 'Get Lucky Single',
                'record_type' => 'single',
                'release_date' => '2013-04-19',
                'explicit_lyrics' => false,
            ],
        ];

        $trackData = [
            $this->makeDeezerTrack(2001, 'Get Lucky', 'USQX91300105', [
                ['id' => 27, 'name' => 'Daft Punk'],
                ['id' => 515, 'name' => 'Pharrell Williams'],
                ['id' => 293, 'name' => 'Nile Rodgers'],
            ]),
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($albumData, $trackData): void {
            $mock->shouldReceive('getArtistAlbums')
                ->with(27)
                ->once()
                ->andReturn($albumData);

            $mock->shouldReceive('getAlbumTracks')
                ->with(302)
                ->once()
                ->andReturn($trackData);
        });

        ImportArtistCatalogJob::dispatchSync($artist);

        $this->assertDatabaseHas('artists', ['deezer_id' => 515, 'name' => 'Pharrell Williams']);
        $this->assertDatabaseHas('artists', ['deezer_id' => 293, 'name' => 'Nile Rodgers']);
        $this->assertDatabaseCount('artist_track', 3);
        $this->assertDatabaseCount('tracks', 1);
    }

    public function test_job_deduplicates_tracks_by_isrc(): void
    {
        $artist = Artist::factory()->create(['deezer_id' => 27, 'name' => 'Daft Punk']);
        $otherArtist = Artist::factory()->create(['deezer_id' => 100, 'name' => 'Other Artist']);

        $albumData = [
            [
                'id' => 303,
                'title' => 'Album A',
                'record_type' => 'album',
                'release_date' => '2020-01-01',
                'explicit_lyrics' => false,
            ],
            [
                'id' => 304,
                'title' => 'Album B',
                'record_type' => 'compile',
                'release_date' => '2021-01-01',
                'explicit_lyrics' => false,
            ],
        ];

        $sharedIsrc = 'USQX91300105';

        $tracksAlbumA = [
            $this->makeDeezerTrack(3001, 'Shared Track', $sharedIsrc, [
                ['id' => 27, 'name' => 'Daft Punk'],
            ]),
        ];

        $tracksAlbumB = [
            $this->makeDeezerTrack(3002, 'Shared Track', $sharedIsrc, [
                ['id' => 27, 'name' => 'Daft Punk'],
                ['id' => 100, 'name' => 'Other Artist'],
            ]),
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($albumData, $tracksAlbumA, $tracksAlbumB): void {
            $mock->shouldReceive('getArtistAlbums')
                ->with(27)
                ->once()
                ->andReturn($albumData);

            $mock->shouldReceive('getAlbumTracks')
                ->with(303)
                ->once()
                ->andReturn($tracksAlbumA);

            $mock->shouldReceive('getAlbumTracks')
                ->with(304)
                ->once()
                ->andReturn($tracksAlbumB);
        });

        ImportArtistCatalogJob::dispatchSync($artist);

        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseCount('artist_track', 2);
    }

    public function test_job_handles_tracks_without_isrc(): void
    {
        $artist = Artist::factory()->create(['deezer_id' => 27, 'name' => 'Daft Punk']);

        $albumData = [
            [
                'id' => 305,
                'title' => 'No ISRC Album',
                'record_type' => 'album',
                'release_date' => '2020-01-01',
                'explicit_lyrics' => false,
            ],
        ];

        $trackData = [
            [
                'id' => 4001,
                'title' => 'Track Without ISRC',
                'duration' => 200,
                'track_position' => 1,
                'explicit_lyrics' => false,
                'isrc' => null,
                'preview' => 'https://example.com/preview.mp3',
                'contributors' => [['id' => 27, 'name' => 'Daft Punk']],
                'artist' => ['id' => 27, 'name' => 'Daft Punk'],
            ],
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($albumData, $trackData): void {
            $mock->shouldReceive('getArtistAlbums')
                ->with(27)
                ->once()
                ->andReturn($albumData);

            $mock->shouldReceive('getAlbumTracks')
                ->with(305)
                ->once()
                ->andReturn($trackData);
        });

        ImportArtistCatalogJob::dispatchSync($artist);

        $this->assertDatabaseHas('tracks', ['deezer_id' => 4001, 'isrc' => null]);
        $this->assertDatabaseCount('tracks', 1);
    }

    public function test_job_is_idempotent(): void
    {
        $artist = Artist::factory()->create(['deezer_id' => 27, 'name' => 'Daft Punk']);

        $albumData = [
            [
                'id' => 306,
                'title' => 'Idempotent Album',
                'record_type' => 'album',
                'release_date' => '2020-01-01',
                'explicit_lyrics' => false,
            ],
        ];

        $trackData = [
            $this->makeDeezerTrack(5001, 'Idempotent Track', 'IDMP00000001', [
                ['id' => 27, 'name' => 'Daft Punk'],
            ]),
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($albumData, $trackData): void {
            $mock->shouldReceive('getArtistAlbums')
                ->with(27)
                ->twice()
                ->andReturn($albumData);

            $mock->shouldReceive('getAlbumTracks')
                ->with(306)
                ->twice()
                ->andReturn($trackData);
        });

        ImportArtistCatalogJob::dispatchSync($artist);
        ImportArtistCatalogJob::dispatchSync($artist);

        $this->assertDatabaseCount('albums', 1);
        $this->assertDatabaseCount('tracks', 1);
        $this->assertDatabaseCount('artist_track', 1);
        $this->assertDatabaseCount('album_artist', 1);
    }
}
