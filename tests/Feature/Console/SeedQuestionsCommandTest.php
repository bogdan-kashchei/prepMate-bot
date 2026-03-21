<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedQuestionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_questions_from_real_json_files(): void
    {
        $this->artisan('bot:seed-questions')
            ->assertSuccessful()
            ->expectsOutputToContain('Done.');

        $this->assertGreaterThan(0, Question::count());
    }

    public function test_seeds_all_four_categories(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        foreach (['frontend', 'backend', 'qa', 'ba'] as $category) {
            $this->assertGreaterThan(
                0,
                Question::where('category', $category)->count(),
                "No questions seeded for category: {$category}",
            );
        }
    }

    public function test_seeds_all_three_levels(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        foreach (['junior', 'middle', 'senior'] as $level) {
            $this->assertGreaterThan(
                0,
                Question::where('level', $level)->count(),
                "No questions seeded for level: {$level}",
            );
        }
    }

    public function test_is_idempotent_on_repeated_calls(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();
        $countAfterFirst = Question::count();

        $this->artisan('bot:seed-questions')->assertSuccessful();

        $this->assertSame($countAfterFirst, Question::count());
    }

    public function test_questions_have_required_fields(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        $question = Question::first();
        $this->assertNotEmpty($question->question_text);
        $this->assertNotNull($question->category);
        $this->assertNotNull($question->level);
        $this->assertIsArray($question->hints);
    }

    public function test_fresh_flag_truncates_existing_questions(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();
        $countAfterSeed = Question::count();

        // Add an extra record not present in the JSON files
        Question::create([
            'category'      => 'frontend',
            'level'         => 'junior',
            'question_text' => 'A unique test-only question?',
            'answer'        => 'Only in test',
            'hints'         => [],
        ]);

        $this->artisan('bot:seed-questions', ['--fresh' => true])->assertSuccessful();

        $this->assertSame($countAfterSeed, Question::count());
        $this->assertDatabaseMissing('questions', ['question_text' => 'A unique test-only question?']);
    }

    public function test_fresh_flag_outputs_deletion_message(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        $this->artisan('bot:seed-questions', ['--fresh' => true])
            ->expectsOutputToContain('Existing questions deleted.')
            ->assertSuccessful();
    }

    public function test_seeded_questions_contain_non_empty_answer(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        $questionsWithAnswer = Question::where('answer', '!=', '')->count();
        $this->assertGreaterThan(0, $questionsWithAnswer);
    }

    public function test_seeded_questions_contain_hints(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        $questionsWithHints = Question::whereNotNull('hints')->get()
            ->filter(fn ($q) => count($q->hints) > 0)
            ->count();

        $this->assertGreaterThan(0, $questionsWithHints);
    }

    public function test_total_question_count_is_600(): void
    {
        $this->artisan('bot:seed-questions')->assertSuccessful();

        // 4 categories × 3 levels × 50 questions = 600 total
        $this->assertSame(600, Question::count());
    }
}
