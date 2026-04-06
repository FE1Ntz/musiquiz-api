<?php

namespace Database\Factories;

use App\Models\GamePlayer;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GamePlayer>
 */
class GamePlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_room_id' => GameRoom::factory(),
            'user_id' => null,
            'nickname' => fake()->userName(),
            'token' => Str::random(64),
            'score' => 0,
            'correct_answers_count' => 0,
            'is_host' => false,
            'is_connected' => true,
        ];
    }

    /**
     * Indicate the player is the host.
     */
    public function asHost(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_host' => true,
        ]);
    }

    /**
     * Indicate the player is a registered user.
     */
    public function asUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate the player is disconnected.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_connected' => false,
        ]);
    }
}
