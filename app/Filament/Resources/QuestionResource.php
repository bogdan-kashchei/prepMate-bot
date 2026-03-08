<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuestionResource\Pages;
use App\Models\Question;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel = 'Questions';
    protected static ?int    $navigationSort  = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category')
                ->options([
                    'frontend' => 'Frontend',
                    'backend'  => 'Backend',
                    'qa'       => 'QA',
                    'ba'       => 'BA',
                ])
                ->required(),

            Forms\Components\Select::make('level')
                ->options([
                    'junior' => 'Junior',
                    'middle' => 'Middle',
                    'senior' => 'Senior',
                ])
                ->required(),

            Forms\Components\Textarea::make('question_text')
                ->label('Question')
                ->required()
                ->rows(3)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('answer')
                ->label('Reference Answer')
                ->rows(5)
                ->columnSpanFull(),

            Forms\Components\Repeater::make('hints')
                ->schema([
                    Forms\Components\TextInput::make('hint')
                        ->label('Hint')
                        ->required(),
                ])
                ->afterStateHydrated(function ($component, $state) {
                    // DB stores plain strings; Repeater needs [{hint: "..."}]
                    if (is_array($state) && !empty($state) && is_string(reset($state))) {
                        $component->state(array_map(fn($h) => ['hint' => $h], $state));
                    }
                })
                ->dehydrateStateUsing(fn($state) => array_values(array_column($state ?? [], 'hint')))
                ->columnSpanFull()
                ->addActionLabel('Add Hint')
                ->defaultItems(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'info'    => 'frontend',
                        'success' => 'backend',
                        'warning' => 'qa',
                        'danger'  => 'ba',
                    ]),

                Tables\Columns\BadgeColumn::make('level')
                    ->colors([
                        'success' => 'junior',
                        'warning' => 'middle',
                        'danger'  => 'senior',
                    ]),

                Tables\Columns\TextColumn::make('question_text')
                    ->label('Question')
                    ->limit(80)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'frontend' => 'Frontend',
                        'backend'  => 'Backend',
                        'qa'       => 'QA',
                        'ba'       => 'BA',
                    ]),

                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'junior' => 'Junior',
                        'middle' => 'Middle',
                        'senior' => 'Senior',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListQuestions::route('/'),
            'create' => Pages\CreateQuestion::route('/create'),
            'edit'   => Pages\EditQuestion::route('/{record}/edit'),
        ];
    }
}
