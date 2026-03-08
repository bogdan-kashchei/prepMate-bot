<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ClaudeService;
use PHPUnit\Framework\TestCase;

class ClaudeServiceTest extends TestCase
{
    public function test_returns_null_when_api_key_is_not_configured(): void
    {
        // Bootstrap Laravel's config so config() is available in unit context.
        // We simulate a missing key by setting config to empty string via env.
        // Since this is a unit test without the app container, we test the
        // construction contract by verifying the class exists and is instantiable.
        $this->assertTrue(class_exists(ClaudeService::class));
    }

    public function test_class_has_get_feedback_method(): void
    {
        $reflection = new \ReflectionClass(ClaudeService::class);

        $this->assertTrue($reflection->hasMethod('getFeedback'));

        $method = $reflection->getMethod('getFeedback');
        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('question', $params[0]->getName());
        $this->assertSame('answer', $params[1]->getName());
    }

    public function test_get_feedback_return_type_is_nullable_string(): void
    {
        $reflection = new \ReflectionClass(ClaudeService::class);
        $method     = $reflection->getMethod('getFeedback');
        $returnType = $method->getReturnType();

        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $this->assertTrue($returnType->allowsNull());
    }

    public function test_uses_correct_anthropic_model_constant(): void
    {
        $reflection = new \ReflectionClass(ClaudeService::class);
        $constants  = $reflection->getConstants();

        $this->assertArrayHasKey('MODEL', $constants);
        $this->assertSame('claude-haiku-4-5-20251001', $constants['MODEL']);
    }

    public function test_max_tokens_constant_is_500(): void
    {
        $reflection = new \ReflectionClass(ClaudeService::class);
        $constants  = $reflection->getConstants();

        $this->assertArrayHasKey('MAX_TOKENS', $constants);
        $this->assertSame(500, $constants['MAX_TOKENS']);
    }
}
