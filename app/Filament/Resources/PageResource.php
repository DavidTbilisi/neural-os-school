<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Wiki';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return (string) Page::where('visibility', Page::VISIBILITY_PUBLIC)->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Published (public) pages';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Publishing gate')
                ->description('The only field you own here. Markdown stays canonical; everything below is read-only and re-syncs on import.')
                ->schema([
                    Forms\Components\Select::make('visibility')
                        ->options(Page::VISIBILITIES)
                        ->required()
                        ->native(false),
                ]),

            Forms\Components\Section::make('Illustration — frozen scene')
                ->description('Curated here, NOT from markdown (so it survives re-import). One image that freezes the whole note; renders as the “Frozen scene” representation on the page. Leave the title blank to fall back to the auto-derived scene.')
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('scene_json.title')
                        ->label('Scene title')
                        ->placeholder('e.g. The fragile bridge')
                        ->maxLength(80),
                    Forms\Components\Textarea::make('scene_json.caption')
                        ->label('Caption')
                        ->rows(2)
                        ->maxLength(400),
                    Forms\Components\Toggle::make('scene_json.sequence')
                        ->label('Ordered sequence (draw → arrows between elements)'),
                    Forms\Components\Repeater::make('scene_json.elements')
                        ->label('Elements')
                        ->schema([
                            Forms\Components\TextInput::make('glyph')->label('Glyph')->placeholder('🌉')->maxLength(8)->columnSpan(1),
                            Forms\Components\TextInput::make('label')->label('Label')->columnSpan(3),
                        ])
                        ->columns(4)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Add element'),
                ]),

            Forms\Components\Section::make('From markdown (read-only)')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')->disabled(),
                    Forms\Components\TextInput::make('slug')->disabled(),
                    Forms\Components\TextInput::make('palace')->disabled(),
                    Forms\Components\TextInput::make('level')->disabled(),
                    Forms\Components\TextInput::make('domain.name')->label('Domain')->disabled(),
                    Forms\Components\TextInput::make('para')->disabled(),
                    Forms\Components\Textarea::make('summary')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('body_md')->label('Body')->disabled()->rows(24)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(['title', 'slug', 'summary'])
                    ->sortable()
                    ->description(fn (Page $r) => $r->slug)
                    ->wrap()
                    ->limit(70),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Page::VISIBILITIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Page::VISIBILITY_PUBLIC => 'success',
                        Page::VISIBILITY_UNLISTED => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain.name')->label('Domain')->badge()->color('info')->toggleable(),
                Tables\Columns\TextColumn::make('palace')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('level')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('para')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('inbound_count')->label('In')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('outbound_count')->label('Out')->numeric()->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('axis_clean')->label('Axes')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('last_updated')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')->options(Page::VISIBILITIES),
                Tables\Filters\SelectFilter::make('domain')->relationship('domain', 'name'),
                Tables\Filters\SelectFilter::make('palace')
                    ->options(array_combine(config('wiki.palaces'), config('wiki.palaces'))),
                Tables\Filters\SelectFilter::make('para')
                    ->options(['project' => 'project', 'area' => 'area', 'resource' => 'resource', 'archive' => 'archive']),
                Tables\Filters\TernaryFilter::make('axis_clean')->label('Axes normalized'),
                Tables\Filters\TernaryFilter::make('is_meta')->label('Registry page')->default(false),
                Tables\Filters\Filter::make('orphans')
                    ->label('Orphans (0 inbound)')
                    ->query(fn (Builder $q) => $q->where('inbound_count', 0)),
                Tables\Filters\Filter::make('has_broken_links')
                    ->label('Has broken links')
                    ->query(fn (Builder $q) => $q->whereHas('outboundLinks', fn (Builder $l) => $l->where('resolved', false))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('publish')
                        ->label('Make public')->icon('heroicon-o-globe-alt')->color('success')->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['visibility' => Page::VISIBILITY_PUBLIC]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('unlist')
                        ->label('Make unlisted')->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['visibility' => Page::VISIBILITY_UNLISTED]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('privatize')
                        ->label('Make private')->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['visibility' => Page::VISIBILITY_PRIVATE]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'view' => Pages\ViewPage::route('/{record}'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
