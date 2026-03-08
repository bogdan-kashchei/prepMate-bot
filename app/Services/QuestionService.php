<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Question;
use App\Models\UserAnswer;
use Throwable;

class QuestionService
{
    /**
     * Return a random question the user hasn't answered yet.
     * If all questions answered, reset history and retry.
     */
    public function getRandomQuestion(string $category, string $level, int $userId): ?array
    {
        try {
            $question = $this->fetchUnseenQuestion($category, $level, $userId);

            if ($question === null) {
                $this->resetHistory($category, $level, $userId);
                $question = $this->fetchUnseenQuestion($category, $level, $userId);
            }

            return $question;
        } catch (Throwable $e) {
            logger()->error('QuestionService::getRandomQuestion — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save an answer to the database.
     */
    public function saveAnswer(
        int $userId,
        int $questionId,
        ?string $answerText,
        ?string $aiFeedback,
        ?string $selfGrade = null,
    ): void {
        try {
            UserAnswer::create([
                'user_id'     => $userId,
                'question_id' => $questionId,
                'answer_text' => $answerText,
                'ai_feedback' => $aiFeedback,
                'self_grade'  => $selfGrade,
            ]);
        } catch (Throwable $e) {
            logger()->error('QuestionService::saveAnswer — ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetchUnseenQuestion(string $category, string $level, int $userId): ?array
    {
        $answeredIds = UserAnswer::where('user_id', $userId)
            ->pluck('question_id')
            ->toArray();

        $question = Question::where('category', $category)
            ->where('level', $level)
            ->when(!empty($answeredIds), fn($q) => $q->whereNotIn('id', $answeredIds))
            ->inRandomOrder()
            ->first();

        if ($question === null) {
            return null;
        }

        return [
            'id'            => $question->id,
            'question_text' => $question->question_text,
            'answer'        => $question->answer,
            'hints'         => $question->hints ?? [],
        ];
    }

    private function resetHistory(string $category, string $level, int $userId): void
    {
        $questionIds = Question::where('category', $category)
            ->where('level', $level)
            ->pluck('id')
            ->toArray();

        UserAnswer::where('user_id', $userId)
            ->whereIn('question_id', $questionIds)
            ->delete();
    }
}
