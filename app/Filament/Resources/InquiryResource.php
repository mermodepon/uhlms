<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InquiryResource\Pages;
use App\Models\Message;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InquiryResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'General Inquiries';

    protected static ?string $modelLabel = 'Inquiry';

    protected static ?string $pluralModelLabel = 'General Inquiries';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('reservation_id');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Message::whereNull('reservation_id')
            ->where('sender_type', 'guest')
            ->where('is_read', false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-envelope-open')
                    ->falseIcon('heroicon-s-envelope')
                    ->trueColor('gray')
                    ->falseColor('warning')
                    ->width('40px'),

                Tables\Columns\TextColumn::make('sender_name')
                    ->label('From')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Message $record): string => $record->is_read ? 'normal' : 'bold'),

                Tables\Columns\TextColumn::make('sender_email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(50)
                    ->weight(fn (Message $record): string => $record->is_read ? 'normal' : 'bold'),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(60)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sender_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'guest'  => 'info',
                        'staff'  => 'warning',
                        'admin'  => 'danger',
                        default  => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_read')
                    ->label('Status')
                    ->options([
                        '0' => 'Unread',
                        '1' => 'Read',
                    ]),
                Tables\Filters\SelectFilter::make('sender_type')
                    ->label('Type')
                    ->options([
                        'guest' => 'Guest',
                        'staff' => 'Staff',
                        'admin' => 'Admin',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View & Reply')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('primary')
                    ->modalHeading(fn (Message $record): string => "Inquiry: {$record->subject}")
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Section::make('Inquiry Details')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('sender_name')
                                        ->label('From')
                                        ->disabled()
                                        ->dehydrated(false),
                                    Forms\Components\TextInput::make('sender_email')
                                        ->label('Email')
                                        ->disabled()
                                        ->dehydrated(false),
                                ]),
                                Forms\Components\TextInput::make('subject')
                                    ->label('Subject')
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Textarea::make('message')
                                    ->label('Message')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->rows(5),
                            ]),
                        Forms\Components\Section::make('Reply')
                            ->schema([
                                Forms\Components\Textarea::make('reply_message')
                                    ->label('Your Reply')
                                    ->placeholder('Type your reply here...')
                                    ->rows(4)
                                    ->maxLength(5000),
                            ]),
                    ])
                    ->fillForm(fn (Message $record): array => [
                        'sender_name'  => $record->sender_name,
                        'sender_email' => $record->sender_email,
                        'subject'      => $record->subject,
                        'message'      => $record->message,
                    ])
                    ->action(function (Message $record, array $data): void {
                        // Mark original as read
                        if (!$record->is_read) {
                            $record->markAsRead();
                        }

                        // Save reply if provided
                        if (!empty($data['reply_message'])) {
                            $user = auth()->user();
                            Message::create([
                                'reservation_id' => null,
                                'sender_id'      => $user->id,
                                'sender_name'    => $user->name,
                                'sender_email'   => $user->email,
                                'sender_type'    => $user->role,
                                'subject'        => 'Re: ' . $record->subject,
                                'message'        => $data['reply_message'],
                            ]);
                        }

                        Notification::make()
                            ->title($data['reply_message'] ? 'Marked as read and reply sent' : 'Marked as read')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Mark as Read & Save Reply'),

                Tables\Actions\Action::make('mark_read')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Message $record): bool => !$record->is_read)
                    ->action(function (Message $record): void {
                        $record->markAsRead();
                        Notification::make()->title('Marked as read')->success()->send();
                    })
                    ->requiresConfirmation(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_all_read')
                    ->label('Mark as Read')
                    ->icon('heroicon-o-check-circle')
                    ->action(function ($records): void {
                        $records->each(fn (Message $r) => $r->markAsRead());
                        Notification::make()->title('Marked as read')->success()->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No inquiries yet')
            ->emptyStateDescription('Guest inquiries submitted without a reservation will appear here.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInquiries::route('/'),
        ];
    }
}
