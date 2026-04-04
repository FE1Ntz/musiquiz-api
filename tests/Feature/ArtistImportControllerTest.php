<?php

namespace Tests\Feature;

use App\Jobs\ImportArtistCatalogJob;
use App\Models\User;
use App\Services\DeezerApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ArtistImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('super-admin', 'web');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    public function test_import_requires_authentication(): void
    {
        $this->postJson(route('admin.artists.import'), ['deezer_id' => 27])
            ->assertUnauthorized();
    }

    public function test_import_requires_super_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 27])
            ->assertForbidden();
    }

    public function test_import_validates_deezer_id_is_required(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deezer_id']);
    }

    public function test_import_validates_deezer_id_is_integer(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deezer_id']);
    }

    public function test_import_returns_422_when_artist_not_found_on_deezer(): void
    {
        $this->mock(DeezerApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getArtist')
                ->with(999999)
                ->once()
                ->andReturn(null);
        });

        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 999999])
            ->assertStatus(422)
            ->assertJson(['message' => 'Artist not found on Deezer.']);
    }

    public function test_import_creates_artist_and_dispatches_job(): void
    {
        Queue::fake();

        $deezerArtistData = [
            'id' => 27,
            'name' => 'Daft Punk',
            'picture_medium' => 'https://example.com/pic.jpg',
            'nb_album' => 10,
            'nb_fan' => 5000000,
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($deezerArtistData): void {
            $mock->shouldReceive('getArtist')
                ->with(27)
                ->once()
                ->andReturn($deezerArtistData);
        });

        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 27])
            ->assertStatus(202)
            ->assertJson([
                'message' => 'Artist imported. Catalog import has been queued.',
                'data' => [
                    'deezer_id' => 27,
                    'name' => 'Daft Punk',
                ],
            ]);

        $this->assertDatabaseHas('artists', [
            'deezer_id' => 27,
            'name' => 'Daft Punk',
        ]);

        Queue::assertPushed(ImportArtistCatalogJob::class, function (ImportArtistCatalogJob $job): bool {
            return $job->artist->deezer_id === 27;
        });
    }

    public function test_import_is_idempotent_for_same_artist(): void
    {
        Queue::fake();

        $deezerArtistData = [
            'id' => 27,
            'name' => 'Daft Punk',
            'picture_medium' => 'https://example.com/pic.jpg',
            'nb_album' => 10,
            'nb_fan' => 5000000,
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($deezerArtistData): void {
            $mock->shouldReceive('getArtist')
                ->with(27)
                ->twice()
                ->andReturn($deezerArtistData);
        });

        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 27])
            ->assertStatus(202);

        $this->actingAs($this->admin)
            ->postJson(route('admin.artists.import'), ['deezer_id' => 27])
            ->assertStatus(202);

        $this->assertDatabaseCount('artists', 1);
    }
}
