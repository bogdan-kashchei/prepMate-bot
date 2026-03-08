<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\ValidateWebhookSecret;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ValidateWebhookSecretTest extends TestCase
{
    private const SECRET = 'super-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => self::SECRET]);

        // Register a lightweight test route using the middleware under test,
        // avoiding any Nutgram/Telegram dependencies.
        Route::post('/test-webhook', fn () => response('OK', 200))
            ->middleware(ValidateWebhookSecret::class);
    }

    public function test_allows_request_with_correct_secret(): void
    {
        $this->postJson('/test-webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => self::SECRET,
        ])->assertOk();
    }

    public function test_rejects_request_with_wrong_secret(): void
    {
        $this->postJson('/test-webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-token',
        ])->assertForbidden();
    }

    public function test_rejects_request_with_missing_header(): void
    {
        $this->postJson('/test-webhook')->assertForbidden();
    }

    public function test_rejects_request_with_empty_secret_header(): void
    {
        $this->postJson('/test-webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => '',
        ])->assertForbidden();
    }

    public function test_rejects_request_when_configured_secret_is_empty(): void
    {
        config(['services.telegram.webhook_secret' => '']);

        $this->postJson('/test-webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => '',
        ])->assertForbidden();
    }

    public function test_secret_comparison_is_case_sensitive(): void
    {
        $this->postJson('/test-webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => strtoupper(self::SECRET),
        ])->assertForbidden();
    }
}
