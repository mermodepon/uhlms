<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GuestsRelationManager extends RelationManager
{
    protected static string $relationship = 'guests';

    protected static ?string $title = 'Guest List';

    protected static ?string $modelLabel = 'guest';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                    ]),
            ])->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('middle_initial')
                    ->label('M.I.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Male' => 'info',
                        'Female' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Refresh Counts')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn () => $this->recalculateGenderCounts())
                    ->tooltip('Recalculate occupant and gender counts based on guest list'),
                Tables\Actions\CreateAction::make()
                    ->label('Add Guest')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-generate full_name from parts
                        $data['full_name'] = trim(
                            ($data['first_name'] ?? '') . ' ' .
                            ($data['middle_initial'] ?? '') . ' ' .
                            ($data['last_name'] ?? '')
                        );
                        return $data;
                    })
                    ->after(fn () => $this->recalculateGenderCounts()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-generate full_name from parts
                        $data['full_name'] = trim(
                            ($data['first_name'] ?? '') . ' ' .
                            ($data['middle_initial'] ?? '') . ' ' .
                            ($data['last_name'] ?? '')
                        );
                        return $data;
                    })
                    ->after(fn () => $this->recalculateGenderCounts()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn () => $this->recalculateGenderCounts()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn () => $this->recalculateGenderCounts()),
                ]),
            ])
            ->emptyStateHeading('No guests added yet')
            ->emptyStateDescription('Add guests to this reservation using the button above.')
            ->emptyStateIcon('heroicon-o-users');
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
        
        // Notify user of the update
        \Filament\Notifications\Notification::make()
            ->title('Counts Updated')
            ->body("Total: {$totalGuests} occupants ({$maleCount} male, {$femaleCount} female)")
            ->success()
            ->send();
    }
}
