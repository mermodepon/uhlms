<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $status = $this->record->status;
        $label = str_replace('_', ' ', ucfirst($status));

        $colorClasses = match (true) {
            $status === 'pending'         => 'background-color:#fef3c7;color:#92400e;',
            $status === 'approved'        => 'background-color:#dbeafe;color:#1e40af;',
            $status === 'confirmed'       => 'background-color:#d1fae5;color:#065f46;',
            $status === 'pending_payment' => 'background-color:#fef3c7;color:#92400e;',
            $status === 'checked_in'      => 'background-color:#dcfce7;color:#166534;',
            $status === 'checked_out'     => 'background-color:#f3f4f6;color:#4b5563;',
            $status === 'cancelled'       => 'background-color:#f3f4f6;color:#4b5563;',
            $status === 'declined'        => 'background-color:#fee2e2;color:#991b1b;',
            default                       => 'background-color:#f3f4f6;color:#4b5563;',
        };

        return new HtmlString(
            'Reservation #' . e($this->record->reference_number) .
            '<br><span style="display:inline-flex;align-items:center;border-radius:6px;padding:2px 12px;margin-top:4px;font-size:0.875rem;font-weight:600;' . $colorClasses . '">' .
            e($label) .
            '</span>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
