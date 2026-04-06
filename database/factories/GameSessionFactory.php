<?php

namespace Database\Factories;

use App\Enums\AnswerMode;
use App\Enums\Difficulty;
use App\Enums\GameStatus;
use App\Models\Artist;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $difficulty = fake()->randomElement(Difficulty::cases());

        return [
            'user_id' => User::factory(),
            'guest_session_id' => null,
            'artist_id' => Artist::factory(),
            'difficulty' => $difficulty,
            'answer_mode' => fake()->randomElement(AnswerMode::cases()),
            'current_round' => 0,
            'total_rounds' => 10,
            'score' => 0,
            'correct_answers_count' => 0,
            'status' => GameStatus::Waiting,
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    /**
     * Indicate the game session is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::InProgress,
            'current_round' => 1,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the game session is finished.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::Finished,
            'current_round' => $attributes['total_rounds'],
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
    }

    /**
     * Indicate the game session is for a guest.
     */
    public function asGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_session_id' => fake()->uuid(),
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
