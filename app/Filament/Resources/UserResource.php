<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Super admins and admins always have access; staff need explicit users_view permission
        return $user->isAdmin() || $user->hasPermission('users_view');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8)
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New Password')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep current password' : null),
                        Forms\Components\Select::make('role')
                            ->options(function () {
                                if (auth()->user()?->isSuperAdmin()) {
                                    return [
                                        'super_admin' => 'Super Administrator',
                                        'admin' => 'Administrator',
                                        'staff' => 'Staff',
                                    ];
                                }

                                return ['staff' => 'Staff'];
                            })
                            ->required()
                            ->default('staff')
                            ->disabled(fn (string $operation) => $operation === 'edit' && ! auth()->user()?->isSuperAdmin())
                            ->dehydrated()
                            ->helperText(fn (string $operation) => ($operation === 'edit' && ! auth()->user()?->isSuperAdmin()) ? 'Only a Super Administrator can change user roles.' : null),
                    ])->columns(2),

                Forms\Components\Section::make('Custom Permissions')
                    ->description('Override this user\'s role-based access with specific per-permission settings. When enabled, these toggles completely replace what the role would normally allow.')
                    ->schema([
                        Forms\Components\Toggle::make('use_custom_permissions')
                            ->label('Enable Custom Permissions')
                            ->helperText('When off, the user\'s role determines access automatically.')
                            ->live()
                            ->dehydrated(false),

                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Fieldset::make('Reservations')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.reservations_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.reservations_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.reservations_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.reservations_delete')->label('Delete')->inline(false),
                                        Forms\Components\Toggle::make('permissions.reservation_discount_settings_view')->label('View Discount Config')->inline(false),
                                        Forms\Components\Toggle::make('permissions.reservation_discount_settings_edit')->label('Edit Discount Config')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Rooms')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.rooms_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.rooms_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.rooms_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.rooms_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Room Types')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.room_types_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.room_types_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.room_types_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.room_types_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Floors')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.floors_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.floors_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.floors_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.floors_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Amenities')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.amenities_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.amenities_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.amenities_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.amenities_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Add-Ons')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.addons_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.addons_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.addons_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.addons_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Users')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.users_view')->label('View')->inline(false),
                                        Forms\Components\Toggle::make('permissions.users_create')->label('Create')->inline(false),
                                        Forms\Components\Toggle::make('permissions.users_edit')->label('Edit')->inline(false),
                                        Forms\Components\Toggle::make('permissions.users_delete')->label('Delete')->inline(false),
                                    ])->columns(4),

                                Forms\Components\Fieldset::make('Stay Logs')
                                    ->schema([
                                        Forms\Components\Toggle::make('permissions.stay_logs_view')->label('View Stay Logs')->inline(false),
                                    ])->columns(1),
                            ])
                            ->hidden(fn ($get) => ! $get('use_custom_permissions')),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'edit' && (auth()->user()?->isSuperAdmin() ?? false))
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'admin' => 'Administrator',
                        'staff' => 'Staff',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'staff' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->modifyQueryUsing(function ($query) {
                // Regular admins only see staff accounts — not other admins or super admins
                if (! auth()->user()?->isSuperAdmin()) {
                    $query->where('role', 'staff');
                }
            })
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(function () {
                        if (auth()->user()?->isSuperAdmin()) {
                            return [
                                'super_admin' => 'Super Admin',
                                'admin' => 'Administrator',
                                'staff' => 'Staff',
                            ];
                        }

                        return ['staff' => 'Staff'];
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record) => auth()->user()?->can('update', $record)),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('User deleted')
                    ->visible(fn (User $record) => auth()->user()?->can('delete', $record))
                    ->disabled(fn (User $record) => $record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                    ->tooltip(fn (User $record) => ($record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                            ? 'This user cannot be deleted because they are linked to room assignments or reservations.'
                            : null
                    ),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
