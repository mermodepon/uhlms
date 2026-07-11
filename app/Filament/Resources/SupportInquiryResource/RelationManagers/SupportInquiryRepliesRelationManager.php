<?php

namespace App\Filament\Resources\SupportInquiryResource\RelationManagers;

use App\Models\SupportInquiryReply;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupportInquiryRepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $title = 'Conversation';

    protected static ?string $icon = 'heroicon-o-chat-bubble-left-right';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('from')
                    ->label('From')
                    ->state(fn (SupportInquiryReply $record) => $record->isFromStaff() ? '🛡 Staff' : '👤 Guest')
                    ->badge()
                    ->color(fn (SupportInquiryReply $record) => $record->isFromStaff() ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('sender_name')
                    ->label('Sender')
                    ->state(fn (SupportInquiryReply $record) => $record->senderName()),
                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent At')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->paginated(false)
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('message')->columnSpanFull(),
            Infolists\Components\TextEntry::make('sender_name')
                ->label('Sender')
                ->state(fn (SupportInquiryReply $record) => $record->senderName()),
            Infolists\Components\TextEntry::make('created_at')->label('Sent At')->dateTime('M d, Y g:i A'),
        ]);
    }
}

