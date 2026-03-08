<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Env::getRepository()->clear('FILAMENT_ADMIN_EMAIL');
        Env::getRepository()->clear('FILAMENT_ADMIN_PASSWORD');
    }

    protected function tearDown(): void
    {
        Env::getRepository()->clear('FILAMENT_ADMIN_EMAIL');
        Env::getRepository()->clear('FILAMENT_ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function test_skips_when_env_vars_not_set(): void
    {
        $this->artisan('bot:create-admin')
            ->expectsOutputToContain('not set')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_creates_admin_user_when_env_vars_present(): void
    {
        Env::getRepository()->set('FILAMENT_ADMIN_EMAIL', 'test@example.com');
        Env::getRepository()->set('FILAMENT_ADMIN_PASSWORD', 'secret123');

        $this->artisan('bot:create-admin')
            ->expectsOutputToContain('Admin created: test@example.com')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_admin_name_defaults_to_admin(): void
    {
        Env::getRepository()->set('FILAMENT_ADMIN_EMAIL', 'named@example.com');
        Env::getRepository()->set('FILAMENT_ADMIN_PASSWORD', 'pass');

        $this->artisan('bot:create-admin')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'named@example.com', 'name' => 'Admin']);
    }

    public function test_password_is_hashed(): void
    {
        Env::getRepository()->set('FILAMENT_ADMIN_EMAIL', 'hash@example.com');
        Env::getRepository()->set('FILAMENT_ADMIN_PASSWORD', 'plaintextpass');

        $this->artisan('bot:create-admin')->assertSuccessful();

        $user = User::where('email', 'hash@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('plaintextpass', $user->password));
        $this->assertNotSame('plaintextpass', $user->password);
    }

    public function test_is_idempotent_on_repeated_calls(): void
    {
        Env::getRepository()->set('FILAMENT_ADMIN_EMAIL', 'idempotent@example.com');
        Env::getRepository()->set('FILAMENT_ADMIN_PASSWORD', 'pass');

        $this->artisan('bot:create-admin')->assertSuccessful();
        $this->artisan('bot:create-admin')
            ->expectsOutputToContain('Admin already exists: idempotent@example.com')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }
}
