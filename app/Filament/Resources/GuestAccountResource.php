<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestAccountResource\Pages;
use App\Http\Controllers\Guest\AuthController as GuestAuthController;
use App\Models\GuestAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class GuestAccountResource extends Resource
{
    protected static ?string $model = GuestAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('guest_accounts_view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('guest_accounts_edit') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('guest_accounts_edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Guest Account')
                ->schema([
                    Forms\Components\TextInput::make('last_name')->maxLength(255)->required(),
                    Forms\Components\TextInput::make('first_name')->maxLength(255)->required(),
                    Forms\Components\TextInput::make('middle_initial')->maxLength(10),
                    Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')->maxLength(30),
                    Forms\Components\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'])->native(false),
                    Forms\Components\TextInput::make('age')->numeric()->minValue(18)->maxValue(120),
                    Forms\Components\Textarea::make('address')->rows(2)->columnSpanFull(),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->minLength(8)
                        ->label(fn (string $operation) => $operation === 'create' ? 'Password' : 'New Password')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep current password.' : null),
                ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Profile')
                ->schema([
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('email')->copyable(),
                    Infolists\Components\TextEntry::make('email_verified_at')->label('Verified')->dateTime()->placeholder('Unverified'),
                    Infolists\Components\TextEntry::make('disabled_at')->label('Disabled')->dateTime()->placeholder('Active'),
                    Infolists\Components\TextEntry::make('phone')->placeholder('-'),
                    Infolists\Components\TextEntry::make('last_login_at')->dateTime()->placeholder('Never'),
                    Infolists\Components\TextEntry::make('address')->columnSpanFull()->placeholder('-'),
                ])->columns(3),
            Infolists\Components\Section::make('Linked Reservations')
                ->description('Profile edits do not rewrite past reservation snapshots.')
                ->schema([
                    Infolists\Components\TextEntry::make('reservation_history')
                        ->label('')
                        ->default(function (GuestAccount $record) {
                            $items = $record->reservations()
                                ->latest()
                                ->limit(10)
                                ->get()
                                ->map(fn ($reservation) => '<li><strong>'.e($reservation->reference_number).'</strong> - '.e(ucfirst(str_replace('_', ' ', $reservation->status))).' - '.e($reservation->check_in_date?->format('M d, Y')).'</li>')
                                ->implode('');

                            return new HtmlString($items ? '<ul class="list-disc pl-5 space-y-1">'.$items.'</ul>' : '<span class="text-gray-500">No linked reservations.</span>');
                        })
                        ->html(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->copyable(),
                Tables\Columns\IconColumn::make('email_verified_at')->label('Verified')->boolean()->getStateUsing(fn (GuestAccount $record) => $record->hasVerifiedEmail()),
                Tables\Columns\TextColumn::make('phone')->toggleable(),
                Tables\Columns\TextColumn::make('reservations_count')->counts('reservations')->label('Reservations')->sortable(),
                Tables\Columns\TextColumn::make('latest_reservation')
                    ->label('Latest Stay')
                    ->getStateUsing(fn (GuestAccount $record) => $record->reservations()->latest('check_in_date')->value('check_in_date'))
                    ->date()
                    ->sortable(false),
                Tables\Columns\TextColumn::make('disabled_at')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (GuestAccount $record) => $record->isDisabled() ? 'Disabled' : 'Active')
                    ->color(fn (string $state) => $state === 'Disabled' ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verified')
                    ->label('Verified')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('email_verified_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Tables\Filters\TernaryFilter::make('disabled')
                    ->label('Disabled')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('disabled_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('disabled_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Tables\Filters\Filter::make('has_reservations')
                    ->query(fn (Builder $query): Builder => $query->has('reservations')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->slideOver(),
                Tables\Actions\EditAction::make()->slideOver()->visible(fn () => auth()->user()?->hasPermission('guest_accounts_edit')),
                Tables\Actions\Action::make('resend_verification')
                    ->label('Resend Verification')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (GuestAccount $record) => ! $record->hasVerifiedEmail() && (auth()->user()?->hasPermission('guest_accounts_edit') ?? false))
                    ->action(function (GuestAccount $record) {
                        GuestAuthController::sendVerificationLinkFor($record);
                        Notification::make()->title('Verification email sent')->success()->send();
                    }),
                Tables\Actions\Action::make('disable')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GuestAccount $record) => ! $record->isDisabled() && (auth()->user()?->hasPermission('guest_accounts_disable') ?? false))
                    ->action(fn (GuestAccount $record) => $record->update(['disabled_at' => now()])),
                Tables\Actions\Action::make('enable')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (GuestAccount $record) => $record->isDisabled() && (auth()->user()?->hasPermission('guest_accounts_disable') ?? false))
                    ->action(fn (GuestAccount $record) => $record->update(['disabled_at' => null])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuestAccounts::route('/'),
            'create' => Pages\CreateGuestAccount::route('/create'),
            'view' => Pages\ViewGuestAccount::route('/{record}'),
            'edit' => Pages\EditGuestAccount::route('/{record}/edit'),
        ];
    }
}
