<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramUser>
 */
class TelegramUserFactory extends Factory
{
    protected $model = TelegramUser::class;

    public function definition(): array
    {
        return [
            'telegram_id'   => $this->faker->unique()->numberBetween(100_000, 999_999_999),
            'username'      => $this->faker->userName(),
            'first_name'    => $this->faker->firstName(),
            'language_code' => 'en',
            'last_seen_at'  => now(),
        ];
    }
}
