<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Question;
use App\Models\TelegramUser;
use App\Models\UserAnswer;
use App\Models\UserSession;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();
    }

    // ── createOrUpdate ────────────────────────────────────────────────────────

    public function test_create_or_update_creates_new_user_and_returns_id(): void
    {
        $id = $this->service->createOrUpdate(
            telegramId: 123456,
            username: 'johndoe',
            firstName: 'John',
            languageCode: 'en',
        );

        $this->assertIsInt($id);
        $this->assertDatabaseHas('telegram_users', [
            'telegram_id' => 123456,
            'username'    => 'johndoe',
            'first_name'  => 'John',
            'language_code' => 'en',
        ]);
    }

    public function test_create_or_update_updates_existing_user(): void
    {
        TelegramUser::factory()->create([
            'telegram_id' => 123456,
            'username'    => 'old_name',
            'first_name'  => 'Old',
        ]);

        $this->service->createOrUpdate(
            telegramId: 123456,
            username: 'new_name',
            firstName: 'New',
        );

        $this->assertDatabaseHas('telegram_users', [
            'telegram_id' => 123456,
            'username'    => 'new_name',
            'first_name'  => 'New',
        ]);
        $this->assertDatabaseCount('telegram_users', 1);
    }

    public function test_create_or_update_returns_same_id_on_update(): void
    {
        $user = TelegramUser::factory()->create(['telegram_id' => 999]);

        $returnedId = $this->service->createOrUpdate(999, 'u', 'F');

        $this->assertSame($user->id, $returnedId);
    }

    public function test_create_or_update_language_code_defaults_to_empty_string(): void
    {
        $this->service->createOrUpdate(555, 'user', 'Name');

        $this->assertDatabaseHas('telegram_users', [
            'telegram_id'   => 555,
            'language_code' => '',
        ]);
    }

    // ── getIdByTelegramId ─────────────────────────────────────────────────────

    public function test_get_id_by_telegram_id_returns_internal_id(): void
    {
        $user = TelegramUser::factory()->create(['telegram_id' => 777]);

        $id = $this->service->getIdByTelegramId(777);

        $this->assertSame($user->id, $id);
    }

    public function test_get_id_by_telegram_id_returns_null_for_unknown_user(): void
    {
        $id = $this->service->getIdByTelegramId(999_999);

        $this->assertNull($id);
    }

    // ── touchLastSeen ─────────────────────────────────────────────────────────

    public function test_touch_last_seen_updates_timestamp(): void
    {
        $user = TelegramUser::factory()->create([
            'last_seen_at' => now()->subDay(),
        ]);

        $before = $user->last_seen_at;

        $this->travel(10)->seconds();
        $this->service->touchLastSeen($user->id);

        $user->refresh();
        $this->assertTrue($user->last_seen_at->isAfter($before));
    }

    // ── saveSession ───────────────────────────────────────────────────────────

    public function test_save_session_creates_session_record(): void
    {
        $user = TelegramUser::factory()->create();

        $this->service->saveSession($user->id, 'frontend', 'junior');

        $this->assertDatabaseHas('user_sessions', [
            'user_id'  => $user->id,
            'category' => 'frontend',
            'level'    => 'junior',
        ]);
    }

    public function test_save_session_updates_existing_session(): void
    {
        $user = TelegramUser::factory()->create();
        UserSession::create(['user_id' => $user->id, 'category' => 'backend', 'level' => 'junior']);

        $this->service->saveSession($user->id, 'frontend', 'senior');

        $this->assertDatabaseHas('user_sessions', [
            'user_id'  => $user->id,
            'category' => 'frontend',
            'level'    => 'senior',
        ]);
        $this->assertDatabaseCount('user_sessions', 1);
    }

    // ── getProgress ───────────────────────────────────────────────────────────

    public function test_get_progress_returns_zeros_for_new_user(): void
    {
        $user = TelegramUser::factory()->create();

        $progress = $this->service->getProgress($user->id);

        $this->assertSame(0, $progress['self_answered']);
        $this->assertSame(0, $progress['viewed_knew']);
        $this->assertSame(0, $progress['viewed_didnt']);
        $this->assertSame([], $progress['by_category']);
    }

    public function test_get_progress_counts_self_answered(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        UserAnswer::factory()->count(3)->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => null,
        ]);

        $progress = $this->service->getProgress($user->id);

        $this->assertSame(3, $progress['self_answered']);
        $this->assertSame(0, $progress['viewed_knew']);
        $this->assertSame(0, $progress['viewed_didnt']);
    }

    public function test_get_progress_counts_viewed_knew_and_didnt_know(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        UserAnswer::factory()->count(2)->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => 'knew',
        ]);
        UserAnswer::factory()->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => 'didnt_know',
        ]);

        $progress = $this->service->getProgress($user->id);

        $this->assertSame(2, $progress['viewed_knew']);
        $this->assertSame(1, $progress['viewed_didnt']);
    }

    public function test_get_progress_calculates_knew_pct_correctly(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create(['category' => 'frontend']);

        UserAnswer::factory()->count(3)->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => 'knew',
        ]);
        UserAnswer::factory()->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => 'didnt_know',
        ]);

        $progress = $this->service->getProgress($user->id);

        // 3 knew out of 4 total = 75%
        $this->assertSame(75, $progress['by_category']['frontend']['knew_pct']);
    }

    public function test_get_progress_knew_pct_is_null_when_no_viewed_answers(): void
    {
        $user     = TelegramUser::factory()->create();
        $question = Question::factory()->create(['category' => 'backend']);

        UserAnswer::factory()->create([
            'user_id'     => $user->id,
            'question_id' => $question->id,
            'self_grade'  => null,
        ]);

        $progress = $this->service->getProgress($user->id);

        $this->assertNull($progress['by_category']['backend']['knew_pct']);
    }

    public function test_get_progress_is_scoped_to_user(): void
    {
        $user1    = TelegramUser::factory()->create();
        $user2    = TelegramUser::factory()->create();
        $question = Question::factory()->create();

        UserAnswer::factory()->count(5)->create([
            'user_id'     => $user2->id,
            'question_id' => $question->id,
            'self_grade'  => 'knew',
        ]);

        $progress = $this->service->getProgress($user1->id);

        $this->assertSame(0, $progress['viewed_knew']);
    }

    public function test_get_progress_groups_by_category(): void
    {
        $user      = TelegramUser::factory()->create();
        $frontendQ = Question::factory()->create(['category' => 'frontend']);
        $backendQ  = Question::factory()->create(['category' => 'backend']);

        UserAnswer::factory()->create([
            'user_id'     => $user->id,
            'question_id' => $frontendQ->id,
            'self_grade'  => 'knew',
        ]);
        UserAnswer::factory()->create([
            'user_id'     => $user->id,
            'question_id' => $backendQ->id,
            'self_grade'  => 'didnt_know',
        ]);

        $progress = $this->service->getProgress($user->id);

        $this->assertArrayHasKey('frontend', $progress['by_category']);
        $this->assertArrayHasKey('backend', $progress['by_category']);
        $this->assertSame(1, $progress['by_category']['frontend']['viewed_knew']);
        $this->assertSame(1, $progress['by_category']['backend']['viewed_didnt']);
    }
}
