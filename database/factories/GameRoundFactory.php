<?php

namespace Database\Factories;

use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRound>
 */
class GameRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snippetStart = fake()->numberBetween(0, 15);

        return [
            'game_session_id' => GameSession::factory(),
            'track_id' => Track::factory(),
            'round_number' => fake()->numberBetween(1, 10),
            'snippet_start_second' => $snippetStart,
            'snippet_end_second' => $snippetStart + 15,
            'preview_url' => fake()->url(),
            'is_completed' => false,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate the round is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate the round is active (started but not completed).
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => false,
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }
}
