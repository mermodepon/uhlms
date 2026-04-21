<?php

namespace App\Filament\Resources\ReservationResource\RelationManagers;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payment Transactions';

    public function form(Form $form): Form
    {
        // Read-only relation manager - no form needed
        return $form
            ->schema([
                Forms\Components\Placeholder::make('note')
                    ->label('')
                    ->content('Payment transactions are managed automatically. Use the main payments section for manual entries.'),
            ]);
    }

    public function table(Table $table): Table
    {
        // Show gateway/deposit columns when toggle is on OR when this reservation has gateway payments
        $showGatewayColumns = fn () => Setting::isOnlinePaymentsEnabled() ||
            $this->getOwnerRecord()->payments()->whereNotNull('gateway')->where('gateway', '!=', '')->exists();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_mode')
                    ->label('Payment Method')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'Cash' => 'success',
                        'GCash', 'Maya', 'Card' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'Manual')
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->visible($showGatewayColumns),
                Tables\Columns\TextColumn::make('gateway_status')
                    ->label('Gateway Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '—')
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->visible($showGatewayColumns),
                Tables\Columns\IconColumn::make('is_deposit')
                    ->label('Deposit')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->visible($showGatewayColumns),
                Tables\Columns\TextColumn::make('gateway_payment_id')
                    ->label('Transaction ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($showGatewayColumns)
                    ->limit(20),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_mode')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'GCash' => 'GCash',
                        'Maya' => 'Maya',
                        'Card' => 'Card',
                        'Bank Transfer' => 'Bank Transfer',
                    ]),
                Tables\Filters\SelectFilter::make('gateway_status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->visible(fn () => Setting::isOnlinePaymentsEnabled() ||
                        $this->getOwnerRecord()->payments()->whereNotNull('gateway')->where('gateway', '!=', '')->exists()),
            ])
            ->headerActions([
                // No create action - payments are managed elsewhere
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Payment Details')
                    ->form([
                        Forms\Components\Section::make('Transaction Information')
                            ->schema([
                                Forms\Components\TextInput::make('id')
                                    ->label('Payment ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->prefix('₱')
                                    ->disabled(),
                                Forms\Components\TextInput::make('payment_mode')
                                    ->label('Payment Method')
                                    ->disabled(),
                                Forms\Components\TextInput::make('created_at')
                                    ->label('Transaction Date')
                                    ->disabled(),
                            ])->columns(2),
                        Forms\Components\Section::make('Gateway Information')
                            ->schema([
                                Forms\Components\TextInput::make('gateway')
                                    ->disabled(),
                                Forms\Components\TextInput::make('gateway_status')
                                    ->label('Status')
                                    ->disabled(),
                                Forms\Components\TextInput::make('gateway_payment_id')
                                    ->label('Gateway Payment ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('gateway_source_id')
                                    ->label('Gateway Source ID')
                                    ->disabled(),
                                Forms\Components\Toggle::make('is_deposit')
                                    ->label('Is Deposit Payment')
                                    ->disabled(),
                            ])->columns(2)
                            ->visible(fn ($record) => $record->gateway &&
                                (Setting::isOnlinePaymentsEnabled() ||
                                $this->getOwnerRecord()->payments()->whereNotNull('gateway')->where('gateway', '!=', '')->exists())),
                        Forms\Components\Section::make('Additional Details')
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->disabled()
                                    ->rows(2),
                                Forms\Components\KeyValue::make('gateway_metadata')
                                    ->label('Gateway Metadata')
                                    ->disabled(),
                            ])->collapsed(),
                    ]),
            ])
            ->bulkActions([
                // No bulk actions for this relation
            ]);
    }
}
