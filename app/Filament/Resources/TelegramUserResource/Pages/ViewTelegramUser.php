<?php

namespace App\Filament\Resources\TelegramUserResource\Pages;

use App\Filament\Resources\TelegramUserResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramUser extends ViewRecord
{
    protected static string $resource = TelegramUserResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('User Info')->schema([
                TextEntry::make('telegram_id')->label('Telegram ID'),
                TextEntry::make('username')
                    ->label('Username')
                    ->formatStateUsing(fn($state) => $state ? "@{$state}" : '—'),
                TextEntry::make('first_name')->label('First Name'),
                TextEntry::make('language_code')
                    ->label('Language')
                    ->formatStateUsing(fn($state) => $state ?: '—'),
                TextEntry::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('created_at')->label('Registered')->dateTime(),
            ])->columns(2),

            Section::make('Answers')->schema([
                RepeatableEntry::make('answers')->schema([
                    TextEntry::make('question.category')->label('Category')->badge(),
                    TextEntry::make('question.level')->label('Level')->badge(),
                    TextEntry::make('question.question_text')->label('Question')->columnSpanFull(),
                    TextEntry::make('answer_text')->label('User Answer')->columnSpanFull(),
                    TextEntry::make('ai_feedback')->label('AI Feedback')->columnSpanFull(),
                    TextEntry::make('self_grade')
                        ->label('Self Grade')
                        ->formatStateUsing(fn($state) => match($state) {
                            'knew'       => 'Knew it',
                            'didnt_know' => "Didn't know",
                            default      => '—',
                        }),
                    TextEntry::make('created_at')->label('Date')->dateTime(),
                ])->columns(2),
            ]),
        ]);
    }
}
