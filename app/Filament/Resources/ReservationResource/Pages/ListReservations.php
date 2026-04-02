<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use Filament\Support\Enums\MaxWidth;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected static ?string $pollingInterval = null;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        parent::mount();

        $req = request();

        if ($status = $req->query('status')) {
            $this->tableFilters ??= [];
            $this->tableFilters['status'] = ['value' => $status];
        }

        if ($req->boolean('near_due')) {
            $this->tableFilters ??= [];
            $this->tableFilters['near_due'] = ['enabled' => true];
        }

        if ($req->boolean('overdue')) {
            $this->tableFilters ??= [];
            $this->tableFilters['overdue'] = ['enabled' => true];
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
