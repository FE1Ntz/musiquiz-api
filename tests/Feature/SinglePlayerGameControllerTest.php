<?php

namespace Tests\Feature;

use App\Events\GameRoundStarted;
use App\Events\GameSessionCreated;
use App\Events\GameSessionFinished;
use App\Models\Artist;
use App\Models\GameSession;
use App\Models\Track;
use App\Models\User;
use App\Services\DeezerApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Tests\TestCase;

class SinglePlayerGameControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createArtistWithTracks(int $trackCount = 10): Artist
    {
        $artist = Artist::factory()->create();

        $tracks = Track::factory()
            ->count($trackCount)
            ->create();

        $artist->tracks()->attach($tracks->pluck('id'));

        $this->mockDeezerApiForTracks($tracks);

        return $artist;
    }

    private function mockDeezerApiForTracks($tracks): void
    {
        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($tracks): void {
            foreach ($tracks as $track) {
                $mock->shouldReceive('getTrack')
                    ->with($track->deezer_id)
                    ->andReturn([
                        'id' => $track->deezer_id,
                        'preview' => "https://cdns-preview.dzcdn.net/stream/{$track->deezer_id}.mp3",
                    ]);
            }
        });
    }

    private function createGameInProgress(?Artist $artist = null): array
    {
        $artist ??= $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated();

        $gameSession = GameSession::find($response->json('game_session.id'));

        return ['response' => $response, 'game_session' => $gameSession, 'artist' => $artist];
    }

    public function test_store_creates_game_session_and_starts_first_round(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'game_session' => [
                    'id', 'difficulty',
                    'current_round', 'total_rounds', 'score', 'status',
                ],
                'current_round' => [
                    'id', 'round_number', 'preview_url',
                    'snippet_start_second', 'snippet_end_second',
                ],
            ])
            ->assertJsonPath('game_session.difficulty', 'easy')
            ->assertJsonPath('game_session.current_round', 1)
            ->assertJsonPath('game_session.total_rounds', 10)
            ->assertJsonPath('game_session.score', 0)
            ->assertJsonPath('game_session.status', 'in_progress');

        Event::assertDispatched(GameSessionCreated::class);
        Event::assertDispatched(GameRoundStarted::class);
    }

    public function test_store_creates_game_with_medium_difficulty(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'medium',
        ])
            ->assertCreated()
            ->assertJsonPath('game_session.difficulty', 'medium');
    }

    public function test_store_creates_game_with_hard_difficulty(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'hard',
        ])
            ->assertCreated()
            ->assertJsonPath('game_session.difficulty', 'hard');
    }

    public function test_store_limits_rounds_to_available_tracks(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks(3);

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ])
            ->assertCreated()
            ->assertJsonPath('game_session.total_rounds', 3);
    }

    public function test_store_fails_for_artist_without_playable_tracks(): void
    {
        Event::fake();
        $artist = Artist::factory()->create();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artist_id');
    }

    public function test_store_fails_for_nonexistent_artist(): void
    {
        $this->postJson(route('games.single-player.store'), [
            'artist_id' => 99999,
            'difficulty' => 'easy',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artist_id');
    }

    public function test_store_fails_for_invalid_difficulty(): void
    {
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'nightmare',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('difficulty');
    }

    public function test_store_fails_without_required_fields(): void
    {
        $this->postJson(route('games.single-player.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['artist_id', 'difficulty']);
    }

    public function test_show_returns_game_state(): void
    {
        Event::fake();
        $game = $this->createGameInProgress();

        $this->getJson(route('games.show', $game['game_session']->id))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'artist', 'difficulty',
                    'current_round', 'total_rounds', 'score', 'status',
                ],
            ]);
    }

    public function test_show_does_not_expose_correct_answer(): void
    {
        Event::fake();
        $game = $this->createGameInProgress();

        $response = $this->getJson(route('games.show', $game['game_session']->id))
            ->assertOk();

        $data = $response->json('data');
        if (isset($data['current_round_data'])) {
            $this->assertArrayNotHasKey('track_title', $data['current_round_data']);
            $this->assertArrayNotHasKey('correct_answer', $data['current_round_data']);
        }
    }

    public function test_show_returns_404_for_nonexistent_session(): void
    {
        $this->getJson(route('games.show', 99999))
            ->assertNotFound();
    }

    public function test_next_round_advances_game(): void
    {
        Event::fake();
        $game = $this->createGameInProgress();
        $gameSession = $game['game_session'];

        // Complete current round
        $currentRound = $gameSession->currentRound();
        $currentRound->update(['is_completed' => true, 'completed_at' => now()]);

        $this->postJson(route('games.next-round', $gameSession->id))
            ->assertOk()
            ->assertJsonPath('game_session.current_round', 2)
            ->assertJsonStructure([
                'game_session',
                'current_round' => [
                    'id', 'round_number', 'preview_url',
                    'snippet_start_second', 'snippet_end_second',
                ],
            ]);
    }

    public function test_finish_ends_game_session(): void
    {
        Event::fake();
        $game = $this->createGameInProgress();

        $this->postJson(route('games.finish', $game['game_session']->id))
            ->assertOk()
            ->assertJsonPath('game_session.status', 'finished');

        Event::assertDispatched(GameSessionFinished::class);
    }

    public function test_store_creates_game_for_authenticated_user(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $artist = $this->createArtistWithTracks();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('games.single-player.store'), [
                'artist_id' => $artist->id,
                'difficulty' => 'easy',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('game_sessions', [
            'user_id' => $user->id,
            'artist_id' => $artist->id,
        ]);
    }

    public function test_store_creates_game_for_guest(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ])
            ->assertCreated();

        $this->assertDatabaseHas('game_sessions', [
            'user_id' => null,
            'artist_id' => $artist->id,
        ]);
    }

    public function test_current_round_preview_url_is_provided(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated();
        $previewUrl = $response->json('current_round.preview_url');
        $this->assertNotNull($previewUrl);
        $this->assertNotEmpty($previewUrl);
    }

    public function test_snippet_timing_is_within_preview_bounds(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated();
        $snippetStart = $response->json('current_round.snippet_start_second');
        $snippetEnd = $response->json('current_round.snippet_end_second');

        $this->assertGreaterThanOrEqual(0, $snippetStart);
        $this->assertLessThanOrEqual(30, $snippetEnd);
        $this->assertEquals(30, $snippetEnd - $snippetStart);
    }

    public function test_store_defaults_to_multiple_choice_mode(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.answer_mode', 'multiple_choice')
            ->assertJsonStructure(['track_options']);

        $trackOptions = $response->json('track_options');
        $this->assertCount(4, $trackOptions);

        foreach ($trackOptions as $option) {
            $this->assertArrayHasKey('id', $option);
            $this->assertArrayHasKey('title', $option);
        }
    }

    public function test_store_creates_text_input_mode_game(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'text_input',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.answer_mode', 'text_input')
            ->assertJsonMissing(['track_options']);
    }

    public function test_store_creates_explicit_multiple_choice_mode_game(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'multiple_choice',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.answer_mode', 'multiple_choice')
            ->assertJsonStructure(['track_options']);
    }

    public function test_store_fails_for_invalid_answer_mode(): void
    {
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'invalid_mode',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('answer_mode');
    }

    public function test_next_round_includes_track_options_for_multiple_choice(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'multiple_choice',
        ]);

        $response->assertCreated();
        $gameSession = GameSession::find($response->json('game_session.id'));

        $currentRound = $gameSession->currentRound();
        $currentRound->update(['is_completed' => true, 'completed_at' => now()]);

        $nextResponse = $this->postJson(route('games.next-round', $gameSession->id));

        $nextResponse->assertOk()
            ->assertJsonStructure(['track_options']);

        $this->assertCount(4, $nextResponse->json('track_options'));
    }

    public function test_next_round_excludes_track_options_for_text_input(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'text_input',
        ]);

        $response->assertCreated();
        $gameSession = GameSession::find($response->json('game_session.id'));

        $currentRound = $gameSession->currentRound();
        $currentRound->update(['is_completed' => true, 'completed_at' => now()]);

        $this->postJson(route('games.next-round', $gameSession->id))
            ->assertOk()
            ->assertJsonMissing(['track_options']);
    }

    public function test_track_options_include_correct_answer(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'answer_mode' => 'multiple_choice',
        ]);

        $response->assertCreated();
        $gameSession = GameSession::find($response->json('game_session.id'));
        $correctTrackId = $gameSession->currentRound()->track_id;

        $optionIds = collect($response->json('track_options'))->pluck('id')->all();
        $this->assertContains($correctTrackId, $optionIds);
    }
}
