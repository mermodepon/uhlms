<?php

namespace App\Filament\Pages;

use App\Models\SupportInquiry;
use App\Models\SupportInquiryReply;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SupportInbox extends Page
{
    protected static string $view = 'filament.pages.support-inbox';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Reservation Management';

    protected static ?string $navigationLabel = 'Support Inbox';

    protected static ?string $title = 'Support Inbox';

    protected static ?int $navigationSort = 10;

    public ?int $selectedId = null;

    public string $replyText = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('support_inquiries_view') ?? false;
    }

    public function getInquiries(): Collection
    {
        return SupportInquiry::with(['guestAccount', 'latestReply'])
            ->withCount('replies')
            ->latest()
            ->get();
    }

    public function getSelectedInquiry(): ?SupportInquiry
    {
        if (!$this->selectedId) {
            return null;
        }

        return SupportInquiry::with([
            'replies' => fn ($q) => $q->with(['sender', 'guestAccount'])->orderBy('created_at'),
            'guestAccount',
        ])->find($this->selectedId);
    }

    public function selectThread(int $id): void
    {
        $this->selectedId = $id;
        $this->replyText = '';
        $this->resetValidation();
    }

    public function canReply(): bool
    {
        return auth()->user()?->hasPermission('support_inquiries_edit') ?? false;
    }

    public function sendReply(): void
    {
        abort_unless($this->canReply(), 403);

        $this->validate(['replyText' => ['required', 'string', 'min:2', 'max:2000']]);

        SupportInquiryReply::create([
            'support_inquiry_id' => $this->selectedId,
            'user_id'            => auth()->id(),
            'guest_account_id'   => null,
            'message'            => trim($this->replyText),
        ]);

        $this->replyText = '';

        Notification::make()
            ->title('Reply sent — visible in the guest\'s account.')
            ->success()
            ->send();
    }
}
