<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FloorResource\Pages;
use App\Models\Floor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class FloorResource extends Resource
{
    protected static ?string $model = Floor::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Room Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Floor Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Ground Floor, 1st Floor'),
                        Forms\Components\TextInput::make('level')
                            ->required()
                            ->numeric()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rooms.room_number')
                    ->label('Rooms')
                    ->searchable()
                    ->badge()
                    ->separator(',')
                    ->limitList(5)
                    ->color('primary'),
                Tables\Columns\IconColumn::make('is_active')
                    ->sortable()
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('level')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Floor deleted')
                    ->disabled(fn (Floor $record) => $record->rooms()->exists())
                    ->tooltip(fn (Floor $record) => $record->rooms()->exists()
                            ? 'This floor cannot be deleted because it is linked to rooms.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // ── Deactivate (admin+) ──────────────────────────
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate selected floors')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (! $record->is_active) {
                                    continue;
                                }
                                $record->update(['is_active' => false]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} floor(s) deactivated")
                                ->success()
                                ->send();
                        }),

                    // ── Activate (admin+) ────────────────────────────
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn () => auth()->user()->isAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Activate selected floors')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    continue;
                                }
                                $record->update(['is_active' => true]);
                                $count++;
                            }
                            Notification::make()
                                ->title("{$count} floor(s) activated")
                                ->success()
                                ->send();
                        }),

                    // ── Bulk Delete (super_admin + password) ─────────
                    Tables\Actions\BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected floors')
                        ->modalDescription('This action is permanent. Floors with linked rooms will be skipped. Enter your password to confirm.')
                        ->modalSubmitActionLabel('Delete permanently')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->label('Confirm your password')
                                ->password()
                                ->revealable()
                                ->required()
                                ->rule('current_password'),
                        ])
                        ->action(function (Collection $records) {
                            $deleted = 0;
                            $skipped = 0;
                            foreach ($records as $record) {
                                if ($record->rooms()->exists()) {
                                    $skipped++;
                                    continue;
                                }
                                $record->delete();
                                $deleted++;
                            }
                            $msg = "{$deleted} floor(s) deleted";
                            if ($skipped > 0) {
                                $msg .= ". {$skipped} skipped (have linked rooms).";
                            }
                            Notification::make()->title($msg)->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFloors::route('/'),
            'create' => Pages\CreateFloor::route('/create'),
            'edit' => Pages\EditFloor::route('/{record}/edit'),
        ];
    }
}
