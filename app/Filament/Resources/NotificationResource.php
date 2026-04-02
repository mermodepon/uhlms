<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->disabled(),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'info' => 'Info',
                                'success' => 'Success',
                                'warning' => 'Warning',
                                'danger' => 'Danger',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('category')
                            ->disabled(),
                        Forms\Components\Toggle::make('is_read')
                            ->label('Read'),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Date & Time')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->searchable()
                    ->colors([
                        'info' => 'info',
                        'success' => 'success',
                        'warning' => 'warning',
                        'danger' => 'danger',
                    ])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('category')
                    ->searchable()
                    ->colors([
                        'reservation' => 'info',
                        'room' => 'primary',
                        'room_assignment' => 'warning',
                        'user' => 'secondary',
                        'system' => 'gray',
                    ]),
                Tables\Columns\IconColumn::make('is_read')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y h:i A')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'danger' => 'Danger',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'reservation' => 'Reservation',
                        'room' => 'Room',
                        'room_assignment' => 'Room Assignment',
                        'user' => 'User',
                        'system' => 'System',
                    ]),
                Tables\Filters\TernaryFilter::make('is_read')
                    ->label('Read Status'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Notification deleted'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_as_read')
                        ->label('Mark as read')
                        ->action(fn ($records) => $records->each->markAsRead()),
                    Tables\Actions\BulkAction::make('mark_as_unread')
                        ->label('Mark as unread')
                        ->action(fn ($records) => $records->each->markAsUnread()),
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotificationTitle('Notifications deleted'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'view' => Pages\ViewNotification::route('/{record}'),
        ];
    }

    public static function getGlobalSearchResultsUrl(): string
    {
        return route('filament.admin.resources.notifications.index');
    }
}
