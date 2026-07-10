<?php

namespace App\Filament\Resources\GlossaryTermResource\Pages;

use App\Filament\Resources\GlossaryTermResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlossaryTerm extends EditRecord
{
    protected static string $resource = GlossaryTermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
