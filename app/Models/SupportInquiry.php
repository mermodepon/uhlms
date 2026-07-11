<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportInquiry extends Model
{
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_ROOM_AVAILABILITY = 'room_availability';
    public const CATEGORY_RESERVATION_HELP = 'reservation_help';
    public const CATEGORY_PAYMENT_CONCERN = 'payment_concern';
    public const CATEGORY_ACCOUNT_HELP = 'account_help';
    public const CATEGORY_FEEDBACK_COMPLAINT = 'feedback_complaint';
    public const CATEGORY_OTHER = 'other';

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_SPAM = 'spam';
    public const STATUS_ARCHIVED = 'archived';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const SOURCE_PUBLIC = 'public';
    public const SOURCE_GUEST_ACCOUNT = 'guest_account';

    protected $fillable = [
        'guest_account_id',
        'name',
        'email',
        'phone',
        'category',
        'subject',
        'message',
        'status',
        'priority',
        'source',
        'internal_notes',
        'handled_by',
        'handled_at',
        'resolved_at',
        'email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'resolved_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_GENERAL => 'General Question',
            self::CATEGORY_ROOM_AVAILABILITY => 'Room / Availability',
            self::CATEGORY_RESERVATION_HELP => 'Reservation Help',
            self::CATEGORY_PAYMENT_CONCERN => 'Payment Concern',
            self::CATEGORY_ACCOUNT_HELP => 'Account Help',
            self::CATEGORY_FEEDBACK_COMPLAINT => 'Feedback / Complaint',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_SPAM => 'Spam',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_NORMAL => 'Normal',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_PUBLIC => 'Public Visitor',
            self::SOURCE_GUEST_ACCOUNT => 'Guest Account',
        ];
    }

    public function guestAccount(): BelongsTo
    {
        return $this->belongsTo(GuestAccount::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportInquiryReply::class, 'support_inquiry_id')->orderBy('created_at');
    }

    public function latestReply(): HasOne
    {
        return $this->hasOne(SupportInquiryReply::class, 'support_inquiry_id')->latestOfMany();
    }

    public function markStatus(string $status, ?User $user = null): void
    {
        $payload = ['status' => $status];

        if ($status !== self::STATUS_NEW) {
            $payload['handled_by'] = $user?->id;
            $payload['handled_at'] = now();
        }

        $payload['resolved_at'] = $status === self::STATUS_RESOLVED ? now() : null;

        $this->forceFill($payload)->save();
    }
}
