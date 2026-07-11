<?php

namespace App\Filament\Resources\ReservationFeedbackResource\Pages;

use App\Filament\Resources\ReservationFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewReservationFeedback extends ViewRecord
{
    protected static string $resource = ReservationFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn () => auth()->user()?->hasPermission('guest_feedback_edit') ?? false),
        ];
    }
}
