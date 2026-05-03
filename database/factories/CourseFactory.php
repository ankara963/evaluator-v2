<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUBJ###'),
            'title' => fake()->words(3, true),
            'semester' => fake()->numberBetween(1, 8),
            'lecture_hours' => fake()->randomElement([1, 2, 3]),
            'laboratory_hours' => fake()->randomElement([0, 1, 2]),
            'credit_units' => fake()->randomElement([2, 3, 4]),
            'is_active' => true,
        ];
    }
}
