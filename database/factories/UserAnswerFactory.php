<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\TelegramUser;
use App\Models\UserAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAnswer>
 */
class UserAnswerFactory extends Factory
{
    protected $model = UserAnswer::class;

    public function definition(): array
    {
        return [
            'user_id'     => TelegramUser::factory(),
            'question_id' => Question::factory(),
            'answer_text' => $this->faker->paragraph(),
            'ai_feedback' => $this->faker->paragraph(),
            'self_grade'  => null,
        ];
    }

    public function knew(): static
    {
        return $this->state(['self_grade' => 'knew', 'answer_text' => null]);
    }

    public function didntKnow(): static
    {
        return $this->state(['self_grade' => 'didnt_know', 'answer_text' => null]);
    }

    public function selfAnswered(): static
    {
        return $this->state(['self_grade' => null]);
    }
}
