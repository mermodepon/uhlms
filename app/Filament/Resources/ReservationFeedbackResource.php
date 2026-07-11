<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationFeedbackResource\Pages;
use App\Models\ReservationFeedback;
use App\Models\RoomType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ReservationFeedbackResource extends Resource
{
    protected static ?string $model = ReservationFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?string $navigationLabel = 'Guest Feedback';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('guest_feedback_view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('guest_feedback_edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Internal Review')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'new' => 'New',
                            'reviewed' => 'Reviewed',
                            'archived' => 'Archived',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Internal Notes')
                        ->rows(5)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Feedback')
                ->schema([
                    Infolists\Components\TextEntry::make('reservation.reference_number')->label('Reservation')->copyable(),
                    Infolists\Components\TextEntry::make('guestAccount.name')->label('Guest Account')->placeholder('-'),
                    Infolists\Components\TextEntry::make('overall_rating')
                        ->label('Overall')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                    Infolists\Components\TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'reviewed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                    Infolists\Components\TextEntry::make('submitted_at')->dateTime('M d, Y g:i A'),
                    Infolists\Components\IconEntry::make('would_stay_again')->boolean()->label('Would Stay Again'),
                    Infolists\Components\TextEntry::make('comments')->columnSpanFull()->placeholder('-'),
                ])->columns(3),
            Infolists\Components\Section::make('Category Ratings')
                ->schema([
                    Infolists\Components\TextEntry::make('cleanliness_rating')
                        ->label('Cleanliness')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                    Infolists\Components\TextEntry::make('comfort_rating')
                        ->label('Comfort')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                    Infolists\Components\TextEntry::make('service_rating')
                        ->label('Staff / Service')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                    Infolists\Components\TextEntry::make('value_rating')
                        ->label('Value')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                    Infolists\Components\TextEntry::make('booking_experience_rating')
                        ->label('Booking Experience')
                        ->formatStateUsing(fn ($state) => self::ratingStars($state))
                        ->html(),
                ])->columns(5),
            Infolists\Components\Section::make('Internal Notes')
                ->schema([
                    Infolists\Components\TextEntry::make('admin_notes')->placeholder('-')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewed By')->placeholder('-'),
                    Infolists\Components\TextEntry::make('reviewed_at')->dateTime('M d, Y g:i A')->placeholder('-'),
                ])->columns(2),
        ]);
    }

    protected static function ratingStars(mixed $state): HtmlString
    {
        if (blank($state)) {
            return new HtmlString('<span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">Not rated</span>');
        }

        $rating = max(0, min(5, (int) $state));
        $stars = '';

        for ($star = 1; $star <= 5; $star++) {
            $color = $star <= $rating ? '#FFC600' : '#D1D5DB';
            $stars .= '<span style="color: '.$color.'; font-size: 1rem; line-height: 1;" aria-hidden="true">&#9733;</span>';
        }

        return new HtmlString(
            '<span class="inline-flex items-center gap-1" aria-label="'.$rating.' out of 5">'
            .$stars.
            '<span class="ml-1 text-sm font-semibold text-gray-700">'.$rating.' / 5</span></span>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['reservation.preferredRoomType', 'guestAccount', 'reviewer']))
            ->columns([
                Tables\Columns\TextColumn::make('reservation.reference_number')->label('Reservation')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guestAccount.name')->label('Guest')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('overall_rating')->label('Rating')->badge()->suffix(' / 5')->sortable()->color('success'),
                Tables\Columns\IconColumn::make('would_stay_again')->boolean()->label('Stay Again'),
                Tables\Columns\TextColumn::make('comments')->label('Comment')->limit(60)->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('reservation.check_in_date')->label('Check-in')->date()->sortable(),
                Tables\Columns\TextColumn::make('reservation.check_out_date')->label('Check-out')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'reviewed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime('M d, Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('overall_rating')
                    ->label('Rating')
                    ->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => 'New', 'reviewed' => 'Reviewed', 'archived' => 'Archived']),
                Tables\Filters\TernaryFilter::make('reviewed')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('reviewed_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('reviewed_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Tables\Filters\SelectFilter::make('room_type')
                    ->label('Room Type')
                    ->options(fn () => RoomType::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('preferred_room_type_id', $data['value']))
                        : $query),
                Tables\Filters\Filter::make('submitted_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')->native(false),
                        Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('submitted_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->slideOver(),
                Tables\Actions\EditAction::make()->slideOver()->visible(fn () => auth()->user()?->hasPermission('guest_feedback_edit') ?? false),
                Tables\Actions\Action::make('mark_reviewed')
                    ->label('Mark Reviewed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ReservationFeedback $record) => $record->status !== 'reviewed' && (auth()->user()?->hasPermission('guest_feedback_edit') ?? false))
                    ->action(fn (ReservationFeedback $record) => $record->markReviewed(auth()->user())),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservationFeedback::route('/'),
            'view' => Pages\ViewReservationFeedback::route('/{record}'),
            'edit' => Pages\EditReservationFeedback::route('/{record}/edit'),
        ];
    }
}
