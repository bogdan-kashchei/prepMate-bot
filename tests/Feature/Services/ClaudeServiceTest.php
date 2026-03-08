<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaudeServiceTest extends TestCase
{
    public function test_get_feedback_returns_null_when_api_key_is_empty(): void
    {
        config(['services.claude.api_key' => '']);

        $service  = new ClaudeService();
        $feedback = $service->getFeedback('What is a closure?', 'A closure is...');

        $this->assertNull($feedback);
    }

    public function test_service_instantiates_with_default_config(): void
    {
        // Ensure no config is set — the service must handle missing key gracefully.
        config(['services.claude.api_key' => '']);

        $service = new ClaudeService();
        $this->assertInstanceOf(ClaudeService::class, $service);
    }

    public function test_get_feedback_is_mockable_for_downstream_tests(): void
    {
        // Verify the service can be mocked so it can be substituted in feature tests
        // without making real HTTP calls.
        $mock = $this->createMock(ClaudeService::class);
        $mock->method('getFeedback')->willReturn('Good answer! You covered the main points.');

        $result = $mock->getFeedback('What is X?', 'X is...');

        $this->assertSame('Good answer! You covered the main points.', $result);
    }
}
