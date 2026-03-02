<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Models\Room;
use App\Models\RoomAssignment;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->successNotificationTitle('Reservation deleted'),
            // Assign Room action on the edit page — uses the current form state for preferred room type
            Actions\Action::make('assign_room')
                ->icon('heroicon-o-key')
                ->color('info')
                ->modalHeading('Assign Room')
                ->visible(fn () => $this->record->status === 'approved' && $this->record->roomAssignments->isEmpty())
                ->disabled(function () {
                    $state = $this->form->getState();
                    $preferred = $state['preferred_room_type_id'] ?? $this->record->preferred_room_type_id;

                    $count = Room::where('status', 'available')
                        ->where('is_active', true)
                        ->when($preferred, fn ($q) => $q->where('room_type_id', $preferred))
                        ->count();

                    return $count === 0;
                })
                ->tooltip(fn () => (
                    $this->form->getState()['preferred_room_type_id'] ?? $this->record->preferred_room_type_id) ?
                        'No available rooms for the selected room type.' :
                        'No available rooms.'
                )
                ->form([
                    Forms\Components\Select::make('room_id')
                        ->label('Select Room')
                        ->options(function () {
                            $state = $this->form->getState();
                            $preferred = $state['preferred_room_type_id'] ?? $this->record->preferred_room_type_id;

                            return Room::where('status', 'available')
                                ->where('is_active', true)
                                ->when($preferred, fn ($q) => $q->where('room_type_id', $preferred))
                                ->with('floor')
                                ->get()
                                ->mapWithKeys(fn ($room) => [$room->id => "Room {$room->room_number} ({$room->floor->name})"]);
                        })
                        ->required()
                        ->searchable(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Assignment Notes')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $room = Room::find($data['room_id'] ?? null);
                    $preferred = $this->form->getState()['preferred_room_type_id'] ?? $this->record->preferred_room_type_id;

                    if (! $room || ! $room->isAvailable()) {
                        Notification::make()
                            ->danger()
                            ->title('Assignment Failed')
                            ->body('Selected room is no longer available.')
                            ->send();

                        return;
                    }

                    if ($preferred && $room->room_type_id !== $preferred) {
                        Notification::make()
                            ->danger()
                            ->title('Assignment Failed')
                            ->body('Selected room does not match the reservation\'s preferred room type.')
                            ->send();

                        return;
                    }

                    RoomAssignment::create([
                        'reservation_id' => $this->record->id,
                        'room_id' => $data['room_id'],
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Room Assigned')
                        ->body('Room has been assigned successfully.')
                        ->send();

                    // Redirect back to the same edit page to refresh relation managers
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record->id]));
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
