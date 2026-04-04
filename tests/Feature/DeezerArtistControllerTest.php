<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DeezerApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeezerArtistControllerTest extends TestCase
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

    public function test_search_requires_authentication(): void
    {
        $this->getJson(route('admin.deezer.artists.search', ['query' => 'Daft Punk']))
            ->assertUnauthorized();
    }

    public function test_search_requires_super_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('admin.deezer.artists.search', ['query' => 'Daft Punk']))
            ->assertForbidden();
    }

    public function test_search_validates_query_is_required(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.deezer.artists.search'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }

    public function test_search_validates_query_minimum_length(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.deezer.artists.search', ['query' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }

    public function test_search_returns_deezer_results(): void
    {
        $mockResponse = [
            'data' => [
                ['id' => 27, 'name' => 'Daft Punk', 'nb_album' => 10, 'nb_fan' => 5000000],
            ],
            'total' => 1,
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($mockResponse): void {
            $mock->shouldReceive('searchArtists')
                ->with('Daft Punk')
                ->once()
                ->andReturn($mockResponse);
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.deezer.artists.search', ['query' => 'Daft Punk']))
            ->assertOk()
            ->assertJson($mockResponse);
    }

    public function test_show_returns_artist_data(): void
    {
        $mockArtist = [
            'id' => 27,
            'name' => 'Daft Punk',
            'nb_album' => 10,
            'nb_fan' => 5000000,
            'picture_medium' => 'https://example.com/pic.jpg',
        ];

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($mockArtist): void {
            $mock->shouldReceive('getArtist')
                ->with(27)
                ->once()
                ->andReturn($mockArtist);
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.deezer.artists.show', ['deezerArtistId' => 27]))
            ->assertOk()
            ->assertJson(['data' => $mockArtist]);
    }

    public function test_show_returns_404_when_artist_not_found(): void
    {
        $this->mock(DeezerApiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getArtist')
                ->with(999999)
                ->once()
                ->andReturn(null);
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.deezer.artists.show', ['deezerArtistId' => 999999]))
            ->assertNotFound()
            ->assertJson(['message' => 'Artist not found on Deezer.']);
    }
}
