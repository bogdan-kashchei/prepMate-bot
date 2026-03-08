<?php

namespace App\Filament\Resources\TelegramUserResource\Pages;

use App\Filament\Resources\TelegramUserResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramUsers extends ListRecords
{
    protected static string $resource = TelegramUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
