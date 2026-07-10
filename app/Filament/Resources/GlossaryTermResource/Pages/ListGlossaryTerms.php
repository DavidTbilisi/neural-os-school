<?php

namespace App\Filament\Resources\GlossaryTermResource\Pages;

use App\Filament\Resources\GlossaryTermResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlossaryTerms extends ListRecords
{
    protected static string $resource = GlossaryTermResource::class;

    protected function getHeaderActions(): array
    {
        return []; // imported from the glossary registry
    }
}
