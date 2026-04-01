<?php

namespace App\Filament\Resources\RoomResource\Pages;

use App\Filament\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRooms extends ListRecords
{
    protected static string $resource = RoomResource::class;

    public function mount(): void
    {
        parent::mount();

        if ($status = request()->query('status')) {
            $this->tableFilters ??= [];
            $this->tableFilters['status'] = ['value' => $status];
        }

        if (request()->boolean('has_occupants')) {
            $this->tableFilters ??= [];
            $this->tableFilters['has_occupants'] = ['enabled' => true];
        }
    }

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
