<?php

namespace Database\Factories;

use App\Models\Album;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
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
            'title' => fake()->sentence(3),
            'cover' => fake()->imageUrl(300, 300),
            'record_type' => fake()->randomElement(['album', 'single', 'ep', 'compile']),
            'release_date' => fake()->date(),
            'explicit_lyrics' => fake()->boolean(20),
        ];
    }
}
