<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->realText(20),
            'author' => fake()->name(),
            'isbn' => fake()->unique()->numerify('978##########'),
            'published_date' => fake()->date(),
            'description' => fake()->realText(200),
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.fake()->numberBetween(1, 99),
        ];
    }
}
