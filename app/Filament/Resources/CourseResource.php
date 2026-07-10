<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourseResource\Pages;
use App\Filament\Resources\CourseResource\RelationManagers\ModulesRelationManager;
use App\Models\Course;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) Course::published()->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Published courses';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create'
                            ? $set('slug', Str::slug($state))
                            : null),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in the /courses/{slug} URL.'),
                    Forms\Components\TextInput::make('subtitle')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->helperText('Markdown. Shown at the top of the course page.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Publishing & placement')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options(Course::STATUSES)
                        ->default(Course::STATUS_DRAFT)
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('domain_id')
                        ->label('Domain')
                        ->relationship('domain', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('prerequisites')
                        ->label('Prerequisite courses (recommended first)')
                        ->relationship(
                            name: 'prerequisites',
                            titleAttribute: 'title',
                            modifyQueryUsing: fn (Builder $query, ?Course $record) => $record
                                ? $query->where('courses.id', '!=', $record->getKey())
                                : $query,
                        )
                        ->multiple()
                        ->preload()
                        ->helperText('Soft gate — surfaced to learners, not enforced (until METER lands).')
                        ->hiddenOn('create') // needs a saved course to attach against
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('source')
                        ->label('Scaffolded from')
                        ->content(fn (?Course $record) => $record?->sourcePage?->title ?? '— hand-authored —')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(['title', 'slug', 'subtitle'])
                    ->sortable()
                    ->description(fn (Course $r) => $r->subtitle ? Str::limit($r->subtitle, 80) : $r->slug)
                    ->weight('bold')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Course::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === Course::STATUS_PUBLISHED ? 'success' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('modules_count')->label('Modules')->counts('modules')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('lessons_count')->label('Lessons')->counts('lessons')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('enrollments_count')->label('Enrolled')->counts('enrollments')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('domain.name')->label('Domain')->badge()->color('info')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(Course::STATUSES),
                Tables\Filters\SelectFilter::make('domain')->relationship('domain', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Publish')->icon('heroicon-o-globe-alt')->color('success')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['status' => Course::STATUS_PUBLISHED]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('unpublish')
                        ->label('Unpublish (draft)')->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['status' => Course::STATUS_DRAFT]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ModulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
