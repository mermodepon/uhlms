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
use App\Models\Guest;

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
                    ->label('Room')
                    ->badge()
                    ->color('info')
                    ->size('lg'),
                Tables\Columns\TextColumn::make('room.roomType.name')
                    ->label('Type')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('room.floor.name')
                    ->label('Floor'),
                Tables\Columns\TextColumn::make('additional_requests')
                    ->label('Additional Services')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'None';
                        }
                        $serviceNames = collect($state)->map(function ($code) {
                            return \App\Models\Service::where('code', $code)->first()?->name ?? $code;
                        })->filter();
                        
                        return $serviceNames->isEmpty() ? 'None' : $serviceNames->implode(', ');
                    })
                    ->color(fn ($state) => empty($state) ? 'gray' : 'success')
                    ->wrap(),
                Tables\Columns\TextColumn::make('payment_mode')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state ?? ''))),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('Amount')
                    ->money('PHP'),
                Tables\Columns\TextColumn::make('assignedByUser.name')
                    ->label('Assigned By'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('reservation'))
            ->filters([])
            ->paginated(false)
            ->heading(null)
            ->description(function () {
                $reservation = $this->getOwnerRecord();
                if (!$reservation->roomAssignments()->exists()) {
                    return 'No room has been assigned to this reservation yet.';
                }
                $guestCount = $reservation->guests()->count();
                $maleCount = $reservation->guests()->where('gender', 'Male')->count();
                $femaleCount = $reservation->guests()->where('gender', 'Female')->count();
                return "Guest List: {$guestCount} total ({$maleCount} male, {$femaleCount} female)";
            })
            ->headerActions([
                Tables\Actions\Action::make('manageGuests')
                    ->label('Manage Guests')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->modalHeading('Manage Guest List')
                    ->modalWidth('5xl')
                    ->form([
                        Forms\Components\Placeholder::make('current_guests')
                            ->label('')
                            ->content(function ($livewire) {
                                $reservation = $livewire->getOwnerRecord();
                                $guests = $reservation->guests;
                                
                                if ($guests->isEmpty()) {
                                    return 'No guests added yet. Use the table below to add guests.';
                                }
                                
                                return view('filament.components.guest-list-summary', ['guests' => $guests]);
                            }),
                    ])
                    ->modalFooterActions([
                        Tables\Actions\Action::make('addGuest')
                            ->label('Add New Guest')
                            ->icon('heroicon-o-plus')
                            ->color('success')
                            ->form([
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('middle_initial')
                                    ->label('M.I.')
                                    ->maxLength(10),
                                Forms\Components\Select::make('gender')
                                    ->label('Gender')
                                    ->options([
                                        'Male' => 'Male',
                                        'Female' => 'Female',
                                        'Other' => 'Other',
                                    ])
                                    ->required(),
                            ])
                            ->action(function ($livewire, array $data) {
                                $reservation = $livewire->getOwnerRecord();
                                
                                $data['full_name'] = trim(
                                    ($data['first_name'] ?? '') . ' ' .
                                    ($data['middle_initial'] ?? '') . ' ' .
                                    ($data['last_name'] ?? '')
                                );
                                
                                $reservation->guests()->create($data);
                                $this->recalculateGenderCounts();
                                
                                Notification::make()
                                    ->success()
                                    ->title('Guest Added')
                                    ->body('Guest has been added to the list.')
                                    ->send();
                            }),
                        Tables\Actions\Action::make('close')
                            ->label('Close')
                            ->color('gray')
                            ->action(fn () => null),
                    ])
                    ->visible(fn () => $this->getOwnerRecord()->roomAssignments()->exists()),
                Tables\Actions\CreateAction::make()
                    ->label('Assign Room')
                    ->icon('heroicon-o-home')
                    ->visible(fn () => !$this->getOwnerRecord()->roomAssignments()->exists())
                    ->action(function ($livewire, array $data) {
                        $reservation = $livewire->getOwnerRecord();

                        // Check if room assignment already exists
                        if ($reservation->roomAssignments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Assignment Failed')
                                ->body('A room has already been assigned to this reservation. Only one room assignment is allowed per reservation.')
                                ->send();

                            return;
                        }

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
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details'),
                Tables\Actions\DeleteAction::make()
                    ->label('Unassign')
                    ->modalHeading('Unassign Room')
                    ->modalDescription('Are you sure you want to unassign this room from the reservation?')
                    ->successNotificationTitle('Room unassigned successfully'),
            ])
            ->emptyStateHeading('No Room Assigned')
            ->emptyStateDescription('This reservation does not have a room assignment yet.')
            ->emptyStateIcon('heroicon-o-home')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Assign Room')
                    ->icon('heroicon-o-plus')
                    ->action(function ($livewire, array $data) {
                        $reservation = $livewire->getOwnerRecord();

                        if ($reservation->roomAssignments()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Assignment Failed')
                                ->body('A room has already been assigned to this reservation. Only one room assignment is allowed per reservation.')
                                ->send();

                            return;
                        }

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
                    }),
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
                        Infolists\Components\TextEntry::make('guest_first_name')
                            ->label('First Name'),
                        Infolists\Components\TextEntry::make('guest_middle_initial')
                            ->label('Middle Initial'),
                        Infolists\Components\TextEntry::make('guest_last_name')
                            ->label('Last Name'),
                        Infolists\Components\TextEntry::make('guest_full_address')
                            ->label('Complete Address')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('guest_contact_number')
                            ->label('Contact Number'),
                        Infolists\Components\TextEntry::make('reservation.guest_gender')
                            ->label('Gender')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'Male' => 'info',
                                'Female' => 'warning',
                                default => 'gray',
                            }),
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
                
                Infolists\Components\Section::make('Guest List')
                    ->schema([
                        Infolists\Components\ViewEntry::make('reservation.guests')
                            ->label('')
                            ->view('filament.infolists.guest-list-table')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->description(function ($record) {
                        $reservation = $record->reservation;
                        $guestCount = $reservation->guests()->count();
                        $maleCount = $reservation->guests()->where('gender', 'Male')->count();
                        $femaleCount = $reservation->guests()->where('gender', 'Female')->count();
                        return "{$guestCount} total guests ({$maleCount} male, {$femaleCount} female)";
                    }),
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

    /**
     * Recalculate male and female guest counts for the reservation
     */
    protected function recalculateGenderCounts(): void
    {
        $reservation = $this->getOwnerRecord();
        
        // Count total guests and by gender
        $totalGuests = $reservation->guests()->count();
        $maleCount = $reservation->guests()->where('gender', 'Male')->count();
        $femaleCount = $reservation->guests()->where('gender', 'Female')->count();
        
        // Update reservation with all counts
        $reservation->update([
            'number_of_occupants' => $totalGuests,
            'num_male_guests' => $maleCount,
            'num_female_guests' => $femaleCount,
        ]);
        
        // If checked in, also update the room assignment
        if ($reservation->status === 'checked_in' && $reservation->roomAssignments()->exists()) {
            $reservation->roomAssignments()->update([
                'num_male_guests' => $maleCount,
                'num_female_guests' => $femaleCount,
            ]);
        }
    }
}
