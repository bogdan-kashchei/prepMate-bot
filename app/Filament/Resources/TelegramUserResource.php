<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramUserResource\Pages;
use App\Models\TelegramUser;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramUserResource extends Resource
{
    protected static ?string $model = TelegramUser::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Bot Users';
    protected static ?int    $navigationSort  = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->formatStateUsing(fn($state) => $state ? "@{$state}" : '—'),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('language_code')
                    ->label('Language')
                    ->formatStateUsing(fn($state) => $state ?: '—'),

                Tables\Columns\TextColumn::make('answers_count')
                    ->label('Answers')
                    ->counts('answers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramUsers::route('/'),
            'view'  => Pages\ViewTelegramUser::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
