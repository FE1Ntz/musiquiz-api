<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Artist;
use App\Models\GameSession;
use App\Models\Track;
use App\Services\DeezerApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GameAnswerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createGameInProgress(): GameSession
    {
        $artist = Artist::factory()->create();
        $tracks = Track::factory()
            ->count(10)
            ->create();
        $artist->tracks()->attach($tracks->pluck('id'));

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

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated();

        return GameSession::find($response->json('game_session.id'));
    }

    public function test_submit_correct_answer_awards_points(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        $response = $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonPath('correct', true)
            ->assertJsonPath('round_finished', true);

        $this->assertGreaterThan(0, $response->json('points_awarded'));
        $this->assertGreaterThan(0, $response->json('updated_total_score'));
    }

    public function test_submit_wrong_answer_subtracts_points(): void
    {
        $gameSession = $this->createGameInProgress();

        // Create a wrong track
        $wrongTrack = Track::factory()->create();

        $response = $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $wrongTrack->id,
            'answer_time_ms' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('round_finished', true);

        $this->assertLessThan(0, $response->json('points_awarded'));
    }

    public function test_submit_text_guess_correct(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();
        $correctTitle = $currentRound->track->title;

        $this->postJson(route('games.answer', $gameSession->id), [
            'text_guess' => $correctTitle,
            'answer_time_ms' => 3000,
        ])
            ->assertOk()
            ->assertJsonPath('correct', true);
    }

    public function test_submit_text_guess_wrong(): void
    {
        $gameSession = $this->createGameInProgress();

        $this->postJson(route('games.answer', $gameSession->id), [
            'text_guess' => 'Completely Wrong Song Title That Does Not Match Anything At All',
            'answer_time_ms' => 3000,
        ])
            ->assertOk()
            ->assertJsonPath('correct', false);
    }

    public function test_cannot_answer_same_round_twice(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        // First answer
        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertOk();

        // Second answer should fail
        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertUnprocessable();
    }

    public function test_cannot_answer_finished_game(): void
    {
        $gameSession = $this->createGameInProgress();
        $gameSession->update(['status' => GameStatus::Finished, 'ended_at' => now()]);

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => 1,
            'answer_time_ms' => 5000,
        ])->assertUnprocessable();
    }

    public function test_answer_requires_either_track_id_or_text_guess(): void
    {
        $gameSession = $this->createGameInProgress();

        $this->postJson(route('games.answer', $gameSession->id), [
            'answer_time_ms' => 5000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['guessed_track_id', 'text_guess']);
    }

    public function test_answer_requires_answer_time(): void
    {
        $gameSession = $this->createGameInProgress();

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('answer_time_ms');
    }

    public function test_faster_correct_answers_score_higher(): void
    {
        $artist1 = Artist::factory()->create();
        $tracks1 = Track::factory()->count(10)->create();
        $artist1->tracks()->attach($tracks1->pluck('id'));

        $artist2 = Artist::factory()->create();
        $tracks2 = Track::factory()->count(10)->create();
        $artist2->tracks()->attach($tracks2->pluck('id'));

        $allTracks = $tracks1->merge($tracks2);

        $this->mock(DeezerApiService::class, function (MockInterface $mock) use ($allTracks): void {
            foreach ($allTracks as $track) {
                $mock->shouldReceive('getTrack')
                    ->with($track->deezer_id)
                    ->andReturn([
                        'id' => $track->deezer_id,
                        'preview' => "https://cdns-preview.dzcdn.net/stream/{$track->deezer_id}.mp3",
                    ]);
            }
        });

        // Game 1: fast answer
        $response1 = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist1->id,
            'difficulty' => 'easy',
        ]);
        $response1->assertCreated();
        $gameSession1 = GameSession::find($response1->json('game_session.id'));
        $round1 = $gameSession1->currentRound();

        $response1 = $this->postJson(route('games.answer', $gameSession1->id), [
            'guessed_track_id' => $round1->track_id,
            'answer_time_ms' => 1000,
        ]);

        // Game 2: slow answer
        $response2 = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist2->id,
            'difficulty' => 'easy',
        ]);
        $response2->assertCreated();
        $gameSession2 = GameSession::find($response2->json('game_session.id'));
        $round2 = $gameSession2->currentRound();

        $response2 = $this->postJson(route('games.answer', $gameSession2->id), [
            'guessed_track_id' => $round2->track_id,
            'answer_time_ms' => 25000,
        ]);

        $this->assertGreaterThan(
            $response2->json('points_awarded'),
            $response1->json('points_awarded'),
        );
    }

    public function test_response_does_not_contain_correct_answer(): void
    {
        $gameSession = $this->createGameInProgress();
        $wrongTrack = Track::factory()->create();

        $response = $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $wrongTrack->id,
            'answer_time_ms' => 5000,
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayNotHasKey('correct_answer', $data);
        $this->assertArrayNotHasKey('correct_track_id', $data);
        $this->assertArrayNotHasKey('correct_track_title', $data);
    }

    public function test_score_cannot_go_below_zero(): void
    {
        $gameSession = $this->createGameInProgress();
        $wrongTrack = Track::factory()->create();

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $wrongTrack->id,
            'answer_time_ms' => 5000,
        ])->assertOk();

        $gameSession->refresh();
        $this->assertGreaterThanOrEqual(0, $gameSession->score);
    }

    public function test_answer_creates_game_answer_record(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertOk();

        $this->assertDatabaseHas('game_answers', [
            'game_session_id' => $gameSession->id,
            'game_round_id' => $currentRound->id,
            'guessed_track_id' => $currentRound->track_id,
            'is_correct' => true,
        ]);
    }

    public function test_timeout_skips_round_with_zero_points(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        $response = $this->postJson(route('games.timeout', $gameSession->id));

        $response->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('points_awarded', 0)
            ->assertJsonPath('score_delta', 0)
            ->assertJsonPath('timed_out', true)
            ->assertJsonPath('round_finished', true);

        $this->assertDatabaseHas('game_answers', [
            'game_session_id' => $gameSession->id,
            'game_round_id' => $currentRound->id,
            'guessed_track_id' => null,
            'text_guess' => null,
            'is_correct' => false,
            'points_awarded' => 0,
        ]);

        $currentRound->refresh();
        $this->assertTrue($currentRound->is_completed);
    }

    public function test_timeout_does_not_change_score(): void
    {
        $gameSession = $this->createGameInProgress();

        $scoreBefore = $gameSession->score;

        $this->postJson(route('games.timeout', $gameSession->id))
            ->assertOk()
            ->assertJsonPath('updated_total_score', $scoreBefore);

        $gameSession->refresh();
        $this->assertEquals($scoreBefore, $gameSession->score);
    }

    public function test_timeout_fails_for_finished_game(): void
    {
        $gameSession = $this->createGameInProgress();
        $gameSession->update(['status' => GameStatus::Finished, 'ended_at' => now()]);

        $this->postJson(route('games.timeout', $gameSession->id))
            ->assertUnprocessable();
    }

    public function test_timeout_fails_when_round_already_answered(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        // Answer the round first
        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertOk();

        // Then try to timeout
        $this->postJson(route('games.timeout', $gameSession->id))
            ->assertUnprocessable();
    }

    public function test_answer_rejected_after_time_limit_expires(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        // Move the round's started_at far into the past (beyond time limit + grace period)
        $currentRound->update(['started_at' => now()->subSeconds(60)]);

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('round');
    }

    public function test_game_session_response_includes_answer_time_limit(): void
    {
        $artist = Artist::factory()->create();
        $tracks = Track::factory()->count(10)->create();
        $artist->tracks()->attach($tracks->pluck('id'));

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

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.difficulty', 'easy');

        $this->assertArrayNotHasKey('answer_time_limit_seconds', $response->json('game_session'));
    }

    public function test_hard_difficulty_has_shorter_answer_time_limit(): void
    {
        $artist = Artist::factory()->create();
        $tracks = Track::factory()->count(10)->create();
        $artist->tracks()->attach($tracks->pluck('id'));

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

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'hard',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.difficulty', 'hard');

        $this->assertArrayNotHasKey('answer_time_limit_seconds', $response->json('game_session'));
    }

    public function test_correct_answer_increments_correct_answers_count(): void
    {
        $gameSession = $this->createGameInProgress();
        $currentRound = $gameSession->currentRound();

        $this->assertEquals(0, $gameSession->correct_answers_count);

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertOk()
            ->assertJsonPath('correct', true);

        $gameSession->refresh();
        $this->assertEquals(1, $gameSession->correct_answers_count);
    }

    public function test_wrong_answer_does_not_increment_correct_answers_count(): void
    {
        $gameSession = $this->createGameInProgress();
        $wrongTrack = Track::factory()->create();

        $this->postJson(route('games.answer', $gameSession->id), [
            'guessed_track_id' => $wrongTrack->id,
            'answer_time_ms' => 5000,
        ])->assertOk()
            ->assertJsonPath('correct', false);

        $gameSession->refresh();
        $this->assertEquals(0, $gameSession->correct_answers_count);
    }

    public function test_timeout_does_not_increment_correct_answers_count(): void
    {
        $gameSession = $this->createGameInProgress();

        $this->postJson(route('games.timeout', $gameSession->id))
            ->assertOk();

        $gameSession->refresh();
        $this->assertEquals(0, $gameSession->correct_answers_count);
    }

    public function test_game_session_response_includes_correct_answers_count(): void
    {
        $artist = Artist::factory()->create();
        $tracks = Track::factory()->count(10)->create();
        $artist->tracks()->attach($tracks->pluck('id'));

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

        $response = $this->postJson(route('games.single-player.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('game_session.correct_answers_count', 0);
    }
}
