<?php

namespace App\Filament\Widgets;

use App\Models\Question;
use App\Models\TelegramUser;
use App\Models\UserAnswer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $todayAnswers = UserAnswer::whereDate('created_at', today())->count();

        return [
            Stat::make('Total Users', TelegramUser::count())
                ->description('Telegram bot users')
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('Total Questions', Question::count())
                ->description('Questions in database')
                ->icon('heroicon-o-question-mark-circle')
                ->color('success'),

            Stat::make('Answers Today', $todayAnswers)
                ->description('Answers submitted today')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('warning'),
        ];
    }
}
