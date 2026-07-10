<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ModulesRelationManager extends RelationManager
{
    protected static string $relationship = 'modules';

    protected static ?string $title = 'Modules & lessons';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(200),
            Forms\Components\Textarea::make('summary')
                ->rows(2),

            Forms\Components\Repeater::make('lessons')
                ->relationship()
                ->orderColumn('sort')
                ->schema([
                    Forms\Components\Select::make('page_id')
                        ->label('Wiki page')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => Page::query()
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%")
                            ->orderBy('title')
                            ->limit(30)
                            ->pluck('title', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value) => Page::find($value)?->title)
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            if ($state && blank($get('title'))) {
                                $set('title', Page::find($state)?->title);
                            }
                        })
                        ->columnSpan(2),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(200)
                        ->columnSpan(2),
                    Forms\Components\Toggle::make('optional')
                        ->helperText('Excluded from progress %')
                        ->inline(false),
                ])
                ->columns(5)
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->reorderableWithButtons()
                ->collapsed()
                ->collapsible()
                ->defaultItems(0)
                ->addActionLabel('Add lesson')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                Tables\Columns\TextColumn::make('title')->weight('bold')->wrap(),
                Tables\Columns\TextColumn::make('lessons_count')->label('Lessons')->counts('lessons')->badge(),
                Tables\Columns\TextColumn::make('summary')->limit(70)->color('gray')->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add module'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
