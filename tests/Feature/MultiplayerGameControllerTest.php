<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Events\MultiplayerGameFinished;
use App\Events\MultiplayerRoundResults;
use App\Events\MultiplayerRoundStarted;
use App\Events\PlayerJoinedRoom;
use App\Jobs\AdvanceToNextRound;
use App\Jobs\ProcessRoundTimeout;
use App\Models\Artist;
use App\Models\GameRoom;
use App\Models\Track;
use App\Services\DeezerApiService;
use App\Services\MultiplayerGameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class MultiplayerGameControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent delayed jobs from running synchronously during tests,
        // which would cascade through all rounds and finish the game instantly.
        Queue::fake([ProcessRoundTimeout::class, AdvanceToNextRound::class]);
    }

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

    /**
     * Create a room and return the room, host token, and artist.
     *
     * @return array{room: GameRoom, host_token: string, artist: Artist}
     */
    private function createRoomWithHost(?Artist $artist = null): array
    {
        Event::fake();
        $artist ??= $this->createArtistWithTracks();

        $response = $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'HostPlayer',
        ]);

        $response->assertCreated();

        $room = GameRoom::find($response->json('room.id'));

        return [
            'room' => $room,
            'host_token' => $response->json('player_token'),
            'artist' => $artist,
        ];
    }

    /**
     * Join a room and return the player token.
     */
    private function joinRoomAsPlayer(string $code, string $nickname): string
    {
        $response = $this->postJson(route('multiplayer.rooms.join', $code), [
            'nickname' => $nickname,
        ]);

        $response->assertOk();

        return $response->json('player_token');
    }

    // ─── Create Room Tests ───

    public function test_create_room_returns_room_and_player_token(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'TestHost',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'room' => [
                    'id', 'code', 'difficulty', 'answer_mode',
                    'status', 'max_players', 'current_round', 'total_rounds',
                    'players',
                ],
                'player' => ['id', 'nickname', 'is_host', 'score'],
                'player_token',
            ])
            ->assertJsonPath('room.status', 'waiting_for_players')
            ->assertJsonPath('room.difficulty', 'easy')
            ->assertJsonPath('player.nickname', 'TestHost')
            ->assertJsonPath('player.is_host', true);

        $this->assertDatabaseHas('game_rooms', [
            'artist_id' => $artist->id,
            'status' => 'waiting_for_players',
        ]);

        Event::assertDispatched(PlayerJoinedRoom::class);
    }

    public function test_create_room_with_custom_max_players(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'medium',
            'nickname' => 'Host',
            'max_players' => 4,
        ])
            ->assertCreated()
            ->assertJsonPath('room.max_players', 4)
            ->assertJsonPath('room.difficulty', 'medium');
    }

    public function test_create_room_fails_for_artist_without_tracks(): void
    {
        $artist = Artist::factory()->create();

        $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'Host',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artist_id');
    }

    public function test_create_room_fails_without_required_fields(): void
    {
        $this->postJson(route('multiplayer.rooms.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['artist_id', 'difficulty', 'nickname']);
    }

    public function test_create_room_fails_for_invalid_difficulty(): void
    {
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'impossible',
            'nickname' => 'Host',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('difficulty');
    }

    public function test_create_room_fails_for_short_nickname(): void
    {
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'A',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');
    }

    public function test_create_room_with_text_input_mode(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'hard',
            'nickname' => 'Host',
            'answer_mode' => 'text_input',
        ])
            ->assertCreated()
            ->assertJsonPath('room.answer_mode', 'text_input');
    }

    // ─── Join Room Tests ───

    public function test_join_room_returns_player_token(): void
    {
        $data = $this->createRoomWithHost();

        $response = $this->postJson(route('multiplayer.rooms.join', $data['room']->code), [
            'nickname' => 'Player2',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['room', 'player', 'player_token'])
            ->assertJsonPath('player.nickname', 'Player2')
            ->assertJsonPath('player.is_host', false);

        $this->assertDatabaseHas('game_players', [
            'game_room_id' => $data['room']->id,
            'nickname' => 'Player2',
            'is_host' => false,
        ]);
    }

    public function test_join_room_fails_with_duplicate_nickname(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.join', $data['room']->code), [
            'nickname' => 'HostPlayer',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');
    }

    public function test_join_room_fails_when_room_is_full(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'Host',
            'max_players' => 2,
        ]);

        $room = GameRoom::find($response->json('room.id'));

        $this->postJson(route('multiplayer.rooms.join', $room->code), [
            'nickname' => 'Player2',
        ])->assertOk();

        $this->postJson(route('multiplayer.rooms.join', $room->code), [
            'nickname' => 'Player3',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room');
    }

    public function test_join_room_fails_when_game_already_started(): void
    {
        $data = $this->createRoomWithHost();

        $data['room']->update(['status' => RoomStatus::InProgress]);

        $this->postJson(route('multiplayer.rooms.join', $data['room']->code), [
            'nickname' => 'LatePlayer',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room');
    }

    public function test_join_nonexistent_room_returns_404(): void
    {
        $this->postJson(route('multiplayer.rooms.join', 'XXXXXX'), [
            'nickname' => 'Player',
        ])->assertNotFound();
    }

    public function test_join_room_fails_without_nickname(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.join', $data['room']->code), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');
    }

    // ─── Show Room Tests ───

    public function test_show_room_returns_room_details(): void
    {
        $data = $this->createRoomWithHost();

        $this->getJson(route('multiplayer.rooms.show', $data['room']->id))
            ->assertOk()
            ->assertJsonStructure([
                'room' => [
                    'id', 'code', 'difficulty', 'answer_mode',
                    'status', 'max_players', 'players',
                ],
            ]);
    }

    public function test_show_room_includes_all_players(): void
    {
        $data = $this->createRoomWithHost();

        $this->joinRoomAsPlayer($data['room']->code, 'Player2');
        $this->joinRoomAsPlayer($data['room']->code, 'Player3');

        $response = $this->getJson(route('multiplayer.rooms.show', $data['room']->id));
        $response->assertOk();

        $players = $response->json('room.players');
        $this->assertCount(3, $players);
    }

    // ─── Leave Room Tests ───

    public function test_player_can_leave_room_during_lobby(): void
    {
        $data = $this->createRoomWithHost();
        $playerToken = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.leave', $data['room']->id), [], [
            'X-Player-Token' => $playerToken,
        ])->assertOk();

        $this->assertDatabaseMissing('game_players', [
            'game_room_id' => $data['room']->id,
            'nickname' => 'Player2',
        ]);
    }

    public function test_host_leaving_lobby_cancels_room(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.leave', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $this->assertEquals(RoomStatus::Cancelled, $data['room']->status);
    }

    public function test_leave_room_fails_without_token(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.leave', $data['room']->id))
            ->assertUnauthorized();
    }

    // ─── Start Game Tests ───

    public function test_host_can_start_game(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $response = $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'room' => ['id', 'status', 'current_round'],
                'current_round' => ['id', 'round_number', 'preview_url'],
            ])
            ->assertJsonPath('room.status', 'in_progress')
            ->assertJsonPath('room.current_round', 1);

        Event::assertDispatched(MultiplayerRoundStarted::class);
    }

    public function test_non_host_cannot_start_game(): void
    {
        $data = $this->createRoomWithHost();
        $playerToken = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $playerToken,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player');
    }

    public function test_cannot_start_game_with_only_one_player(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room');
    }

    public function test_cannot_start_already_started_game(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        // Start once
        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        // Try to start again
        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room');
    }

    public function test_start_game_creates_game_session_and_rounds(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $this->assertNotNull($data['room']->game_session_id);

        $gameSession = $data['room']->gameSession;
        $this->assertEquals($data['room']->total_rounds, $gameSession->rounds()->count());
    }

    // ─── Submit Answer Tests ───

    public function test_player_can_submit_answer(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        $response = $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], [
            'X-Player-Token' => $data['host_token'],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['correct', 'points_awarded']);

        $this->assertDatabaseHas('game_player_answers', [
            'game_round_id' => $currentRound->id,
            'is_correct' => true,
        ]);
    }

    public function test_player_cannot_answer_twice_in_same_round(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // First answer
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        // Second answer
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], [
            'X-Player-Token' => $data['host_token'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('round');
    }

    public function test_answer_without_token_is_unauthorized(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        // Start game first so there's a valid round to answer
        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ])->assertUnauthorized();
    }

    // ─── Timeout Tests ───

    public function test_player_can_timeout(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $this->postJson(route('multiplayer.rooms.timeout', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])
            ->assertOk()
            ->assertJsonPath('correct', false)
            ->assertJsonPath('timed_out', true);
    }

    public function test_timeout_job_auto_submits_for_missing_players(): void
    {
        Queue::fake([ProcessRoundTimeout::class, AdvanceToNextRound::class]);

        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Only host answers — player 2 doesn't
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], ['X-Player-Token' => $data['host_token']])->assertOk();

        // Execute the timeout job manually
        $job = new ProcessRoundTimeout($data['room'], 1);
        $job->handle(app(MultiplayerGameService::class));

        // Player 2 should have an auto-submitted answer
        $player2 = $data['room']->players()->where('nickname', 'Player2')->first();
        $this->assertDatabaseHas('game_player_answers', [
            'game_player_id' => $player2->id,
            'game_round_id' => $currentRound->id,
            'is_correct' => false,
            'guessed_track_id' => null,
        ]);

        // Round should be completed
        $currentRound->refresh();
        $this->assertTrue($currentRound->is_completed);
    }

    // ─── Round Completion Tests ───

    public function test_round_completes_when_all_players_answer(): void
    {
        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Host answers
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 3000,
        ], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        // Player 2 answers
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], [
            'X-Player-Token' => $player2Token,
        ])->assertOk();

        $currentRound->refresh();
        $this->assertTrue($currentRound->is_completed);

        Event::assertDispatched(MultiplayerRoundResults::class);
    }

    // ─── Next Round Tests ───

    public function test_round_completion_dispatches_advance_job(): void
    {
        Queue::fake([AdvanceToNextRound::class, ProcessRoundTimeout::class]);

        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Both players answer to complete round 1
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 3000,
        ], ['X-Player-Token' => $data['host_token']])->assertOk();

        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], ['X-Player-Token' => $player2Token])->assertOk();

        $currentRound->refresh();
        $this->assertTrue($currentRound->is_completed);

        Queue::assertPushed(AdvanceToNextRound::class, function (AdvanceToNextRound $job) use ($data) {
            return $job->room->id === $data['room']->id && $job->completedRoundNumber === 1;
        });
    }

    public function test_starting_round_dispatches_timeout_job(): void
    {
        Queue::fake([ProcessRoundTimeout::class]);

        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        Queue::assertPushed(ProcessRoundTimeout::class);
    }

    public function test_advance_job_moves_to_next_round(): void
    {
        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Both players answer
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 3000,
        ], ['X-Player-Token' => $data['host_token']])->assertOk();

        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], ['X-Player-Token' => $player2Token])->assertOk();

        // Manually execute the AdvanceToNextRound job
        $job = new AdvanceToNextRound($data['room'], 1);
        $job->handle(app(MultiplayerGameService::class));

        $data['room']->refresh();
        $this->assertEquals(2, $data['room']->current_round);
        $this->assertTrue($data['room']->isInProgress());

        Event::assertDispatched(MultiplayerRoundStarted::class);
    }

    public function test_advance_job_is_idempotent(): void
    {
        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Both players answer
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 3000,
        ], ['X-Player-Token' => $data['host_token']])->assertOk();

        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], ['X-Player-Token' => $player2Token])->assertOk();

        $service = app(MultiplayerGameService::class);

        // Execute twice — second call should be a no-op
        $job1 = new AdvanceToNextRound($data['room'], 1);
        $job1->handle($service);

        $data['room']->refresh();
        $this->assertEquals(2, $data['room']->current_round);

        // Second execution — room already on round 2, should skip
        $job2 = new AdvanceToNextRound($data['room'], 1);
        $job2->handle($service);

        $data['room']->refresh();
        $this->assertEquals(2, $data['room']->current_round);
    }

    public function test_show_room_is_read_only(): void
    {
        Queue::fake([AdvanceToNextRound::class, ProcessRoundTimeout::class]);

        $data = $this->createRoomWithHost();
        $player2Token = $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $data['room']->id), [], [
            'X-Player-Token' => $data['host_token'],
        ])->assertOk();

        $data['room']->refresh();
        $currentRound = $data['room']->gameSession->rounds()->where('round_number', 1)->first();

        // Both players answer to complete round 1
        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 3000,
        ], ['X-Player-Token' => $data['host_token']])->assertOk();

        $this->postJson(route('multiplayer.rooms.answer', $data['room']->id), [
            'guessed_track_id' => $currentRound->track_id,
            'answer_time_ms' => 5000,
        ], ['X-Player-Token' => $player2Token])->assertOk();

        // showRoom should NOT advance the round — it's read-only now
        $response = $this->getJson(route('multiplayer.rooms.show', $data['room']->id));
        $response->assertOk()
            ->assertJsonPath('room.current_round', 1);
    }

    // ─── Leaderboard Tests ───

    public function test_leaderboard_returns_sorted_players(): void
    {
        $data = $this->createRoomWithHost();
        $this->joinRoomAsPlayer($data['room']->code, 'Player2');

        $response = $this->getJson(route('multiplayer.rooms.leaderboard', $data['room']->id));

        $response->assertOk()
            ->assertJsonStructure([
                'leaderboard' => [
                    '*' => ['player_id', 'nickname', 'score', 'correct_answers_count', 'is_host'],
                ],
            ]);

        $this->assertCount(2, $response->json('leaderboard'));
    }

    // ─── Game Finished Tests ───

    public function test_game_finishes_after_last_round(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks(2);

        $response = $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'Host',
        ]);

        $room = GameRoom::find($response->json('room.id'));
        $hostToken = $response->json('player_token');

        $player2Token = $this->joinRoomAsPlayer($room->code, 'Player2');

        $this->postJson(route('multiplayer.rooms.start', $room->id), [], [
            'X-Player-Token' => $hostToken,
        ])->assertOk();

        $service = app(MultiplayerGameService::class);

        // Complete both rounds
        for ($roundNum = 1; $roundNum <= 2; $roundNum++) {
            $room->refresh();
            $currentRound = $room->gameSession->rounds()->where('round_number', $roundNum)->first();

            $this->postJson(route('multiplayer.rooms.answer', $room->id), [
                'guessed_track_id' => $currentRound->track_id,
                'answer_time_ms' => 3000,
            ], ['X-Player-Token' => $hostToken])->assertOk();

            $this->postJson(route('multiplayer.rooms.answer', $room->id), [
                'guessed_track_id' => $currentRound->track_id,
                'answer_time_ms' => 5000,
            ], ['X-Player-Token' => $player2Token])->assertOk();

            // Execute the AdvanceToNextRound job (simulates the delayed job firing)
            $job = new AdvanceToNextRound($room, $roundNum);
            $job->handle($service);
        }

        $room->refresh();
        $this->assertEquals(RoomStatus::Finished, $room->status);

        // Verify leaderboard is returned on show
        $showResponse = $this->getJson(route('multiplayer.rooms.show', $room->id));
        $showResponse->assertOk()
            ->assertJsonPath('room.status', 'finished')
            ->assertJsonStructure(['leaderboard']);

        Event::assertDispatched(MultiplayerGameFinished::class);
    }

    // ─── Room Code Tests ───

    public function test_room_code_is_six_characters(): void
    {
        Event::fake();
        $artist = $this->createArtistWithTracks();

        $response = $this->postJson(route('multiplayer.rooms.store'), [
            'artist_id' => $artist->id,
            'difficulty' => 'easy',
            'nickname' => 'Host',
        ]);

        $code = $response->json('room.code');
        $this->assertNotNull($code);
        $this->assertEquals(6, strlen($code));
    }

    // ─── Player Token Security Tests ───

    public function test_player_token_is_not_exposed_in_room_resource(): void
    {
        $data = $this->createRoomWithHost();

        $response = $this->getJson(route('multiplayer.rooms.show', $data['room']->id));
        $response->assertOk();

        $players = $response->json('room.players');
        foreach ($players as $player) {
            $this->assertArrayNotHasKey('token', $player);
        }
    }

    public function test_invalid_token_is_rejected(): void
    {
        $data = $this->createRoomWithHost();

        $this->postJson(route('multiplayer.rooms.leave', $data['room']->id), [], [
            'X-Player-Token' => 'invalid-token-here',
        ])->assertUnauthorized();
    }
}
