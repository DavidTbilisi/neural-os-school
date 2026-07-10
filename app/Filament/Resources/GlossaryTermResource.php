<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlossaryTermResource\Pages;
use App\Models\GlossaryTerm;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlossaryTermResource extends Resource
{
    protected static ?string $model = GlossaryTerm::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Wiki';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('term')
            ->columns([
                Tables\Columns\TextColumn::make('term')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('owner_slug')->label('Owner page')->searchable()
                    ->url(fn (GlossaryTerm $r) => $r->owner_page_id ? PageResource::getUrl('view', ['record' => $r->owner_page_id]) : null)
                    ->color(fn (GlossaryTerm $r) => $r->owner_page_id ? 'primary' : 'danger')
                    ->tooltip(fn (GlossaryTerm $r) => $r->owner_page_id ? null : 'No page for this owner slug'),
                Tables\Columns\TextColumn::make('section')->limit(60)->toggleable()->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('unowned')
                    ->label('Missing owner page')
                    ->query(fn (Builder $q) => $q->whereNull('owner_page_id')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlossaryTerms::route('/'),
        ];
    }
}
