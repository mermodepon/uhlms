<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AdminMfa;
use App\Support\SecurityAudit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

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
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (string $operation): bool => $operation === 'edit' && ! auth()->user()?->isSuperAdmin())
                            ->dehydrated(fn (string $operation): bool => $operation !== 'edit' || (auth()->user()?->isSuperAdmin() ?? false))
                            ->helperText(fn (string $operation): ?string => ($operation === 'edit' && ! auth()->user()?->isSuperAdmin())
                                ? 'Only a Super Administrator can change login emails.'
                                : null),
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

                Forms\Components\Section::make('Access Summary')
                    ->schema([
                        Forms\Components\Placeholder::make('access_role')
                            ->label('Role')
                            ->content(fn (?User $record): string => match ($record?->role) {
                                'super_admin' => 'Super Administrator',
                                'admin' => 'Administrator',
                                'staff' => 'Staff',
                                default => '—',
                            }),
                        Forms\Components\Placeholder::make('access_mode')
                            ->label('Access mode')
                            ->content(fn (?User $record): string => $record?->permissions === null
                                ? 'Using role defaults'
                                : 'Custom permissions enabled'),
                        Forms\Components\Placeholder::make('enabled_permissions')
                            ->label('Enabled permissions')
                            ->content(fn (?User $record): string => $record?->permissions === null
                                ? 'Role defaults'
                                : (string) count(array_filter($record->permissions ?? []))),
                    ])
                    ->columns(3)
                    ->visible(fn (string $operation): bool => $operation === 'edit' && (auth()->user()?->isSuperAdmin() ?? false)),

                Forms\Components\Section::make('Access & Permissions')
                    ->description('Override this user\'s role-based access with specific per-permission settings. When enabled, these toggles completely replace what the role would normally allow.')
                    ->schema([
                        Forms\Components\Toggle::make('use_custom_permissions')
                            ->label('Enable Custom Permissions')
                            ->helperText('When off, the user\'s role determines access automatically.')
                            ->live()
                            ->dehydrated(false),

                        Forms\Components\Grid::make(1)
                            ->schema(array_map(
                                fn (string $group, array $permissions): Forms\Components\Fieldset => Forms\Components\Fieldset::make($group)
                                    ->schema(array_map(
                                        fn (array $permission): Forms\Components\Toggle => Forms\Components\Toggle::make('permissions.'.$permission['key'])
                                            ->label($permission['label'])
                                            ->inline(false),
                                        $permissions,
                                    ))
                                    ->columns(min(4, count($permissions))),
                                array_keys(User::permissionGroups()),
                                array_values(User::permissionGroups()),
                            ))
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
                Tables\Actions\Action::make('reset_mfa')
                    ->label('Reset MFA')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This removes the user\'s authenticator and recovery codes. They must enroll again at their next administrative login.')
                    ->visible(fn (User $record): bool => (auth()->user()?->isSuperAdmin() ?? false)
                        && $record->id !== auth()->id()
                        && AdminMfa::isEnabled($record))
                    ->action(function (User $record): void {
                        abort_unless(auth()->user()?->isSuperAdmin(), 403);
                        abort_if($record->id === auth()->id(), 403, 'You cannot reset your own MFA.');
                        abort_unless(AdminMfa::isEnabled(auth()->user()) && AdminMfa::isRecent(), 403, 'Recent MFA confirmation is required.');

                        app(SecurityAudit::class)->record('mfa_administrative_reset_requested', [
                            'actor_id' => auth()->id(),
                            'target_user_id' => $record->id,
                        ]);
                        app(DisableTwoFactorAuthentication::class)($record);
                        app(SecurityAudit::class)->alert(
                            'mfa_administrative_reset',
                            'Administrative MFA reset',
                            'A super administrator reset another user\'s MFA configuration.',
                            ['actor_id' => auth()->id(), 'target_user_id' => $record->id],
                            'danger',
                            true,
                        );
                        Notification::make()->title('MFA reset')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('User deleted')
                    ->visible(fn (User $record) => auth()->user()?->can('delete', $record))
                    ->disabled(fn (User $record) => $record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                    ->tooltip(fn (User $record) => ($record->roomAssignments()->exists() || $record->reviewedReservations()->exists())
                            ? 'This user cannot be deleted because they are linked to room assignments or reservations.'
                            : null
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // ── Bulk Delete (super_admin + password) ─────────
                    Tables\Actions\BulkAction::make('bulk_delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn () => auth()->user()->isSuperAdmin())
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected users')
                        ->modalDescription('This action is permanent. Users linked to reservations or assignments will be skipped. Your own account and other super admins will be skipped. Enter your password to confirm.')
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
                                if (
                                    $record->id === auth()->id() ||
                                    $record->isSuperAdmin() ||
                                    $record->roomAssignments()->exists() ||
                                    $record->reviewedReservations()->exists()
                                ) {
                                    $skipped++;

                                    continue;
                                }
                                $record->delete();
                                $deleted++;
                            }
                            $msg = "{$deleted} user(s) deleted";
                            if ($skipped > 0) {
                                $msg .= ". {$skipped} skipped (linked to data or protected).";
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'permissions' => Pages\PermissionsReference::route('/permissions'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
