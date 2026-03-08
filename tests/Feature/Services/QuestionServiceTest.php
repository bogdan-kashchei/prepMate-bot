<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Question;
use App\Models\TelegramUser;
use App\Models\UserAnswer;
use App\Services\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuestionService();
    }

    // ── getRandomQuestion ─────────────────────────────────────────────────────

    public function test_get_random_question_returns_array_with_expected_keys(): void
    {
        $user     = TelegramUser::factory()->create();
        Question::factory()->create(['category' => 'frontend', 'level' => 'junior']);

        $result = $this->service->getRandomQuestion('frontend', 'junior', $user->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('question_text', $result);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('hints', $result);
    }

    public function test_get_random_question_returns_null_when_no_questions_exist(): void
    {
        $user = TelegramUser::factory()->create();

        $result = $this->service->getRandomQuestion('frontend', 'junior', $user->id);

        $this->assertNull($result);
    }

    public function test_get_random_question_excludes_already_seen_questions(): void
    {
        $user      = TelegramUser::factory()->create();
        $question1 = Question::factory()->create(['category' => 'backend', 'level' => 'junior']);
        $question2 = Question::factory()->create(['category' => 'backend', 'level' => 'junior']);

        // Mark question1 as answered
        UserAnswer::create([
            'user_id'     => $user->id,
            'question_id' => $question1->id,
            'answer_text' => 'my answer',
            'ai_feedback' => null,
        ]);

        $result = $this->service->getRandomQuestion('backend', 'junior', $user->id);

        $this->assertNotNull($result);
        $this->assertSame($question2->id, $result['id']);
    }

    public function test_get_random_question_resets_history_when_all_seen(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create(['category' => 'qa', 'level' => 'middle']);

        // Mark the only question as seen
        UserAnswer::create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'answer_text' => 'answer',
            'ai_feedback' => null,
        ]);

        $result = $this->service->getRandomQuestion('qa', 'middle', $user->id);

        // History should be reset and the question returned again
        $this->assertNotNull($result);
        $this->assertSame($question->id, $result['id']);

        // Previous answer record should have been deleted
        $this->assertDatabaseCount('user_answers', 0);
    }

    public function test_get_random_question_only_returns_matching_category_and_level(): void
    {
        $user        = TelegramUser::factory()->create();
        $frontendQ   = Question::factory()->create(['category' => 'frontend', 'level' => 'junior']);
        Question::factory()->create(['category' => 'backend', 'level' => 'junior']);
        Question::factory()->create(['category' => 'frontend', 'level' => 'senior']);

        $result = $this->service->getRandomQuestion('frontend', 'junior', $user->id);

        $this->assertSame($frontendQ->id, $result['id']);
    }

    public function test_get_random_question_hints_is_array(): void
    {
        $user = TelegramUser::factory()->create();
        Question::factory()->create([
            'category' => 'ba',
            'level'    => 'senior',
            'hints'    => ['Hint one', 'Hint two'],
        ]);

        $result = $this->service->getRandomQuestion('ba', 'senior', $user->id);

        $this->assertIsArray($result['hints']);
        $this->assertCount(2, $result['hints']);
    }

    public function test_get_random_question_returns_null_for_other_users_seen_questions(): void
    {
        $user1    = TelegramUser::factory()->create();
        $user2    = TelegramUser::factory()->create();
        $question = Question::factory()->create(['category' => 'frontend', 'level' => 'junior']);

        // user2 has seen the question — user1 should still get it
        UserAnswer::create([
            'user_id'     => $user2->id,
            'question_id' => $question->id,
            'answer_text' => 'text',
            'ai_feedback' => null,
        ]);

        $result = $this->service->getRandomQuestion('frontend', 'junior', $user1->id);

        $this->assertNotNull($result);
        $this->assertSame($question->id, $result['id']);
    }

    // ── saveAnswer ────────────────────────────────────────────────────────────

    public function test_save_answer_creates_user_answer_record(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        $this->service->saveAnswer(
            userId: $user->id,
            questionId: $question->id,
            answerText: 'My answer text',
            aiFeedback: 'AI feedback here',
        );

        $this->assertDatabaseHas('user_answers', [
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'answer_text' => 'My answer text',
            'ai_feedback' => 'AI feedback here',
            'self_grade'  => null,
        ]);
    }

    public function test_save_answer_stores_self_grade(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        $this->service->saveAnswer(
            userId: $user->id,
            questionId: $question->id,
            answerText: null,
            aiFeedback: null,
            selfGrade: 'knew',
        );

        $this->assertDatabaseHas('user_answers', [
            'user_id'    => $user->id,
            'self_grade' => 'knew',
        ]);
    }

    public function test_save_answer_allows_null_answer_text_and_feedback(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        $this->service->saveAnswer(
            userId: $user->id,
            questionId: $question->id,
            answerText: null,
            aiFeedback: null,
        );

        $this->assertDatabaseHas('user_answers', [
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'answer_text' => null,
            'ai_feedback' => null,
        ]);
    }

    public function test_save_answer_allows_multiple_answers_for_same_question(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        $this->service->saveAnswer($user->id, $question->id, 'first', null);
        $this->service->saveAnswer($user->id, $question->id, 'second', null);

        $this->assertDatabaseCount('user_answers', 2);
    }
}
