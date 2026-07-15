<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportInquiryResource\Pages;
use App\Filament\Resources\SupportInquiryResource\RelationManagers\SupportInquiryRepliesRelationManager;
use App\Models\SupportInquiry;
use App\Models\SupportInquiryReply;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupportInquiryResource extends Resource
{
    protected static ?string $model = SupportInquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?string $navigationLabel = 'Support Inquiries';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('support_inquiries_view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('support_inquiries_edit') ?? false;
    }

    public static function canReply(SupportInquiry $record): bool
    {
        return $record->guest_account_id !== null && static::canEdit($record);
    }

    public static function authorizeReply(SupportInquiry $record): void
    {
        abort_unless(static::canReply($record), 403);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->options(SupportInquiry::statusOptions())
                ->required(),
            Forms\Components\Select::make('priority')
                ->options(SupportInquiry::priorityOptions())
                ->required(),
            Forms\Components\Textarea::make('internal_notes')
                ->label('Internal Notes')
                ->rows(5)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Inquiry')
                ->schema([
                    Infolists\Components\TextEntry::make('subject')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('name')->label('Guest Name'),
                    Infolists\Components\TextEntry::make('email')->copyable(),
                    Infolists\Components\TextEntry::make('phone')->placeholder('-'),
                    Infolists\Components\TextEntry::make('category')
                        ->formatStateUsing(fn (string $state) => SupportInquiry::categoryOptions()[$state] ?? $state)
                        ->badge(),
                    Infolists\Components\TextEntry::make('guestAccount.name')->label('Guest Account')->placeholder('-'),
                    Infolists\Components\TextEntry::make('message')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('created_at')->label('Submitted')->dateTime('M d, Y g:i A'),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('guestAccount'))
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable()->sortable()->limit(45),
                Tables\Columns\TextColumn::make('name')->label('Guest')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('category')
                    ->formatStateUsing(fn (string $state) => SupportInquiry::categoryOptions()[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime('M d, Y g:i A')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->options(SupportInquiry::categoryOptions()),
                Tables\Filters\Filter::make('submitted_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->native(false),
                        Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->slideOver(),
                self::replyAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function replyAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reply')
            ->label('Reply to Guest')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (SupportInquiry $record) => static::canReply($record))
            ->form([
                Forms\Components\Textarea::make('message')
                    ->label('Your Reply')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000)
                    ->rows(5),
            ])
            ->action(function (SupportInquiry $record, array $data): void {
                static::authorizeReply($record);

                SupportInquiryReply::create([
                    'support_inquiry_id' => $record->id,
                    'user_id' => auth()->id(),
                    'guest_account_id' => null,
                    'message' => $data['message'],
                ]);

                Notification::make()
                    ->title('Reply added — the guest will see it in their account.')
                    ->success()
                    ->send();
            });
    }

    public static function getRelationManagers(): array
    {
        return [
            SupportInquiryRepliesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportInquiries::route('/'),
            'view' => Pages\ViewSupportInquiry::route('/{record}'),
            'edit' => Pages\EditSupportInquiry::route('/{record}/edit'),
        ];
    }
}
