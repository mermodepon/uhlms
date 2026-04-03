<?php

namespace App\Filament\Pages;

use App\Models\ForceDeletionLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ForceDeletionLogs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 35;

    protected static ?string $title = 'Force Deletion Logs';

    protected static ?string $navigationLabel = 'Force Deletion Logs';

    protected static string $view = 'filament.pages.force-deletion-logs';

    public static function canAccess(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ForceDeletionLog::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Reference #')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('guest_name')
                    ->label('Guest Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status at Deletion')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'checked_in' => 'success',
                        'checked_out' => 'gray',
                        'declined' => 'danger',
                        'cancelled' => 'gray',
                        'pending_payment' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn (ForceDeletionLog $record) => $record->reason),

                Tables\Columns\TextColumn::make('deleted_by_name')
                    ->label('Deleted By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('related_counts')
                    ->label('Records Deleted')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state)) {
                            return '-';
                        }
                        $parts = [];
                        foreach ($state as $key => $count) {
                            if ($count > 0) {
                                $parts[] = $count.' '.str_replace('_', ' ', $key);
                            }
                        }

                        return implode(', ', $parts) ?: 'None';
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Deleted At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_snapshot')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ForceDeletionLog $record) => "Deleted: {$record->reference_number}")
                    ->modalContent(fn (ForceDeletionLog $record) => new HtmlString(
                        view('filament.pages.force-deletion-log-detail', ['record' => $record])->render()
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateHeading('No force deletions recorded')
            ->emptyStateDescription('Force deletion logs will appear here when a super admin permanently deletes a reservation.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
