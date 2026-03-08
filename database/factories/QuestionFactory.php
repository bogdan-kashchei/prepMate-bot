<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'category'      => $this->faker->randomElement(['frontend', 'backend', 'qa', 'ba']),
            'level'         => $this->faker->randomElement(['junior', 'middle', 'senior']),
            'question_text' => $this->faker->sentence() . '?',
            'answer'        => $this->faker->paragraph(),
            'hints'         => [$this->faker->sentence(), $this->faker->sentence()],
        ];
    }

    public function frontend(): static
    {
        return $this->state(['category' => 'frontend']);
    }

    public function backend(): static
    {
        return $this->state(['category' => 'backend']);
    }

    public function junior(): static
    {
        return $this->state(['level' => 'junior']);
    }

    public function senior(): static
    {
        return $this->state(['level' => 'senior']);
    }
}
