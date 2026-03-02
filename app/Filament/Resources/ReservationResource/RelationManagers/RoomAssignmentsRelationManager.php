<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Room;
use App\Models\Service;

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
                Tables\Columns\TextColumn::make('guest_full_name')
                    ->label('Guest Name')
                    ->formatStateUsing(fn ($record) => 
                        trim(($record->guest_first_name ?? '') . ' ' . 
                             ($record->guest_middle_initial ?? '') . ' ' . 
                             ($record->guest_last_name ?? ''))
                    )
                    ->toggleable(),
                Tables\Columns\TextColumn::make('additional_requests')
                    ->label('Additional Services')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'None';
                        }
                        // Convert service codes to readable names
                        $serviceNames = collect($state)->map(function ($code) {
                            return \App\Models\Service::where('code', $code)->first()?->name ?? $code;
                        })->filter();
                        
                        return $serviceNames->isEmpty() ? 'None' : $serviceNames->implode(', ');
                    })
                    ->color(fn ($state) => empty($state) ? 'gray' : 'success')
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_mode')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? '')))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->toggleable(),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Room Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('room.room_number')
                            ->label('Room Number'),
                        Infolists\Components\TextEntry::make('room.roomType.name')
                            ->label('Room Type'),
                        Infolists\Components\TextEntry::make('room.floor.name')
                            ->label('Floor'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Guest Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('guest_last_name')
                            ->label('Last Name'),
                        Infolists\Components\TextEntry::make('guest_first_name')
                            ->label('First Name'),
                        Infolists\Components\TextEntry::make('guest_middle_initial')
                            ->label('Middle Initial'),
                        Infolists\Components\TextEntry::make('guest_full_address')
                            ->label('Complete Address')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('guest_contact_number')
                            ->label('Contact Number'),
                        Infolists\Components\TextEntry::make('nationality')
                            ->label('Nationality'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Identification & Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('id_type')
                            ->label('ID Type')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? ''))),
                        Infolists\Components\TextEntry::make('id_number')
                            ->label('ID Number'),
                        Infolists\Components\IconEntry::make('is_student')
                            ->label('Student')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_senior_citizen')
                            ->label('Senior Citizen')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_pwd')
                            ->label('PWD')
                            ->boolean(),
                    ])->columns(5),
                
                Infolists\Components\Section::make('Stay Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('purpose_of_stay')
                            ->label('Purpose of Stay'),
                        Infolists\Components\TextEntry::make('num_male_guests')
                            ->label('Male Guests'),
                        Infolists\Components\TextEntry::make('num_female_guests')
                            ->label('Female Guests'),
                        Infolists\Components\TextEntry::make('detailed_checkin_datetime')
                            ->label('Check-in Date & Time')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('detailed_checkout_datetime')
                            ->label('Check-out Date & Time')
                            ->dateTime(),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Additional Services & Payment')
                    ->schema([
                        Infolists\Components\TextEntry::make('additional_requests')
                            ->label('Additional Services')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'None requested';
                                }
                                $serviceNames = collect($state)->map(function ($code) {
                                    $service = Service::where('code', $code)->first();
                                    if ($service) {
                                        return $service->name . ($service->price > 0 ? " ({$service->formatted_price})" : ' (Free)');
                                    }
                                    return $code;
                                })->filter();
                                
                                return $serviceNames->isEmpty() ? 'None requested' : $serviceNames->implode(', ');
                            })
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('payment_mode')
                            ->label('Payment Mode')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? ''))),
                        Infolists\Components\TextEntry::make('payment_amount')
                            ->label('Payment Amount')
                            ->money('PHP'),
                        Infolists\Components\TextEntry::make('payment_or_number')
                            ->label('OR Number'),
                    ])->columns(3),
                
                Infolists\Components\Section::make('Assignment Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('assignedByUser.name')
                            ->label('Assigned By'),
                        Infolists\Components\TextEntry::make('assigned_at')
                            ->label('Assigned At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->columnSpanFull()
                            ->placeholder('No notes'),
                    ])->columns(2),
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
