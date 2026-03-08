<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature   = 'bot:create-admin';
    protected $description = 'Create the Filament admin user from ADMIN_EMAIL / ADMIN_PASSWORD env vars (idempotent)';

    public function handle(): int
    {
        $email    = env('FILAMENT_ADMIN_EMAIL');
        $password = env('FILAMENT_ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->warn('ADMIN_EMAIL or ADMIN_PASSWORD not set — skipping admin creation.');
            return self::SUCCESS;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => bcrypt($password)],
        );

        $this->info($user->wasRecentlyCreated ? "Admin created: {$email}" : "Admin already exists: {$email}");

        return self::SUCCESS;
    }
}
