<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
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
            'name' => fake()->name(),
            'cover' => fake()->imageUrl(300, 300),
            'albums_count' => fake()->numberBetween(1, 50),
            'fans' => fake()->numberBetween(100, 1000000),
        ];
    }
}
