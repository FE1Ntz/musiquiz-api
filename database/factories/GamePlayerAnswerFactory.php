<?php

namespace Database\Factories;

use App\Models\GamePlayer;
use App\Models\GamePlayerAnswer;
use App\Models\GameRound;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GamePlayerAnswer>
 */
class GamePlayerAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_player_id' => GamePlayer::factory(),
            'game_round_id' => GameRound::factory(),
            'guessed_track_id' => Track::factory(),
            'text_guess' => null,
            'answer_time_ms' => fake()->numberBetween(1000, 30000),
            'is_correct' => false,
            'points_awarded' => 0,
        ];
    }

    /**
     * Indicate the answer is correct.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
            'points_awarded' => fake()->numberBetween(50, 1000),
        ]);
    }

    /**
     * Indicate the answer is a text guess.
     */
    public function textGuess(): static
    {
        return $this->state(fn (array $attributes) => [
            'guessed_track_id' => null,
            'text_guess' => fake()->sentence(3),
        ]);
    }
}
