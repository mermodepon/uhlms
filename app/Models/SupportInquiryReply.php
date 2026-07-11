<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportInquiryReply extends Model
{
    protected $fillable = [
        'support_inquiry_id',
        'user_id',
        'guest_account_id',
        'message',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(SupportInquiry::class, 'support_inquiry_id');
    }

    /** Staff sender (User) */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Guest sender (GuestAccount) */
    public function guestAccount(): BelongsTo
    {
        return $this->belongsTo(GuestAccount::class, 'guest_account_id');
    }

    public function isFromStaff(): bool
    {
        return $this->user_id !== null;
    }

    public function senderName(): string
    {
        return $this->isFromStaff()
            ? ($this->sender?->name ?? 'Staff')
            : ($this->guestAccount?->name ?? 'Guest');
    }
}

