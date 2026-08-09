<?php

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\StudyProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyProgress>
 */
class StudyProgressFactory extends Factory {
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'user_id' => User::factory(),
            'flashcard_id' => Flashcard::factory(),
            'review_count' => fake()->numberBetween(0, 100),
            'average_score' => fake()->numberBetween(0, 100),
            'last_reviewed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'next_review_due_at' => fake()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
