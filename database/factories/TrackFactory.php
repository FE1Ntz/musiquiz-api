<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deezer_id' => fake()->unique()->numberBetween(100000, 9999999),
            'title' => fake()->sentence(4),
            'duration' => fake()->numberBetween(60, 600),
            'track_position' => fake()->numberBetween(1, 20),
            'explicit_lyrics' => fake()->boolean(20),
            'isrc' => fake()->unique()->regexify('[A-Z]{2}[A-Z0-9]{3}[0-9]{7}'),
            'album_id' => Album::factory(),
        ];
    }

    /**
     * Indicate that the track has no ISRC.
     */
    public function withoutIsrc(): static
    {
        return $this->state(fn (array $attributes) => [
            'isrc' => null,
        ]);
    }
}
