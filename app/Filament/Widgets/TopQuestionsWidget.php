<?php

namespace App\Filament\Widgets;

use App\Models\UserAnswer;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopQuestionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top 5 Most Answered Questions';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                UserAnswer::query()
                    ->selectRaw('question_id, COUNT(*) as answer_count')
                    ->groupBy('question_id')
                    ->orderByDesc('answer_count')
                    ->limit(5)
                    ->with('question')
            )
            ->columns([
                Tables\Columns\TextColumn::make('question.category')
                    ->label('Category')
                    ->badge(),

                Tables\Columns\TextColumn::make('question.level')
                    ->label('Level')
                    ->badge(),

                Tables\Columns\TextColumn::make('question.question_text')
                    ->label('Question')
                    ->limit(80),

                Tables\Columns\TextColumn::make('answer_count')
                    ->label('Times Answered')
                    ->sortable(),
            ]);
    }
}
