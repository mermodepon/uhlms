<?php

namespace App\Filament\Resources\StayLogResource\Pages;

use App\Filament\Resources\StayLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStayLogs extends ListRecords
{
    protected static string $resource = StayLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
            Actions\CreateAction::make(),
        ];
    }
}
