<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\ReviewLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewLog>
 */
class ReviewLogFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'flashcard_id' => Flashcard::factory(),
            'score' => fake()->numberBetween(0, 100),
        ];
    }
}
