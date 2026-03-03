<?php

namespace App\Filament\Resources\ConversationResource\Pages;

use App\Filament\Resources\ConversationResource;
use App\Models\Message;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;
    
    protected static ?string $pollingInterval = '10s';

    protected static string $view = 'filament.resources.conversation-resource.pages.view-conversation';

    public string $quickReply = '';

    public function sendQuickReply()
    {
        if (empty(trim($this->quickReply))) {
            return;
        }

        Message::create([
            'reservation_id' => $this->record->id,
            'sender_id' => auth()->id(),
            'sender_type' => auth()->user()->role,
            'message' => $this->quickReply,
        ]);
        
        // Mark all guest messages as read when replying
        $this->record->messages()
            ->where('is_read', false)
            ->where('sender_type', 'guest')
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        $this->quickReply = '';
        
        $this->dispatch('notification', title: 'Message sent successfully', icon: 'success');
    }

    public function deleteMessage($messageId)
    {
        $message = Message::where('id', $messageId)
            ->where('reservation_id', $this->record->id)
            ->first();

        if ($message) {
            $message->delete();
            
            \Filament\Notifications\Notification::make()
                ->title('Message deleted successfully')
                ->success()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_message')
                ->label('Send Message')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Your Message')
                        ->required()
                        ->rows(4)
                        ->placeholder('Type your message here...')
                        ->autofocus(),
                ])
                ->action(function (array $data) {
                    Message::create([
                        'reservation_id' => $this->record->id,
                        'sender_id' => auth()->id(),
                        'sender_type' => auth()->user()->role,
                        'message' => $data['message'],
                    ]);
                    
                    // Mark all guest messages as read when replying
                    $this->record->messages()
                        ->where('is_read', false)
                        ->where('sender_type', 'guest')
                        ->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                })
                ->successNotificationTitle('Message sent successfully'),
            Actions\Action::make('mark_all_read')
                ->label('Mark All Read')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $this->record->messages()
                        ->where('is_read', false)
                        ->update([
                            'is_read' => true,
                            'read_at' => now(),
                        ]);
                })
                ->visible(fn () => $this->record->messages()->where('is_read', false)->exists())
                ->successNotificationTitle('All messages marked as read'),
        ];
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Auto-mark guest messages as read when viewing conversation
        $this->record->messages()
            ->where('is_read', false)
            ->where('sender_type', 'guest')
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }
}
