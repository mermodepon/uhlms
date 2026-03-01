<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Room;

class RoomAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'roomAssignments';

    protected static ?string $title = 'Room Assignments';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_id')
                    ->options(function () {
                        $reservation = $this->getOwnerRecord();

                        $query = Room::query()
                            ->where('status', 'available')
                            ->where('is_active', true);

                        if ($reservation && $reservation->preferred_room_type_id) {
                            $query->where('room_type_id', $reservation->preferred_room_type_id);
                        }

                        return $query->pluck('room_number', 'id')->toArray();
                    })
                    ->required()
                    ->searchable(),
                Forms\Components\Textarea::make('notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('room.room_number')
                    ->label('Room'),
                Tables\Columns\TextColumn::make('room.roomType.name')
                    ->label('Type'),
                Tables\Columns\TextColumn::make('room.floor.name')
                    ->label('Floor'),
                Tables\Columns\TextColumn::make('assignedByUser.name')
                    ->label('Assigned By'),
                Tables\Columns\TextColumn::make('assigned_at')
                    ->dateTime()
                    ->label('Assigned At'),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->action(function ($livewire, array $data) {
                        $reservation = $livewire->getOwnerRecord();

                        $room = Room::find($data['room_id'] ?? null);
                        if (! $room || ! $room->isAvailable()) {
                            Notification::make()
                                ->danger()
                                ->title('Assignment Failed')
                                ->body('Selected room is no longer available.')
                                ->send();

                            return;
                        }

                        if ($reservation && $reservation->preferred_room_type_id && $room->room_type_id !== $reservation->preferred_room_type_id) {
                            Notification::make()
                                ->danger()
                                ->title('Assignment Failed')
                                ->body('Selected room does not match the reservation\'s preferred room type.')
                                ->send();

                            return;
                        }

                        $data['assigned_by'] = auth()->id();
                        $data['assigned_at'] = now();

                        $livewire->getRelationship()->create($data);

                        Notification::make()
                            ->success()
                            ->title('Room Assigned')
                            ->body('Room has been assigned successfully.')
                            ->send();

                        // Reinitialize the table so the new assignment appears without redirect
                        if (method_exists($livewire, 'resetTable')) {
                            $livewire->resetTable();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public int $roomAssignmentsRefreshCounter = 0;

    protected function getListeners(): array
    {
        return array_merge(parent::getListeners(), [
            'roomAssigned' => 'handleRoomAssigned',
        ]);
    }

    public function handleRoomAssigned(): void
    {
        $this->roomAssignmentsRefreshCounter++;
    }
}
