<?php

namespace Database\Factories;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\RoomStatus;
use App\Models\Artist;
use App\Models\GameRoom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GameRoom>
 */
class GameRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(6)),
            'artist_id' => Artist::factory(),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'answer_mode' => fake()->randomElement(AnswerMode::cases()),
            'status' => RoomStatus::WaitingForPlayers,
            'max_players' => 8,
            'game_session_id' => null,
            'current_round' => 0,
            'total_rounds' => 10,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Indicate the room is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoomStatus::InProgress,
            'current_round' => 1,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the room is finished.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoomStatus::Finished,
            'current_round' => $attributes['total_rounds'],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    /**
     * Set a specific difficulty.
     */
    public function withDifficulty(Difficulty $difficulty): static
    {
        return $this->state(fn (array $attributes) => [
            'difficulty' => $difficulty,
        ]);
    }
}
