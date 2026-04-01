<?php

namespace App\Models;

use App\Events\NotificationCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'title',
        'message',
        'type',
        'category',
        'notifiable_type',
        'notifiable_id',
        'created_by',
        'action_url',
        'is_read',
        'read_at',
    ];

    protected $dispatchesEvents = [
        'created' => NotificationCreated::class,
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }

    public function markAsUnread(): void
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public static function createNotification(
        Model $notifiable,
        string $title,
        string $message,
        string $type = 'info',
        ?string $category = null,
        ?string $actionUrl = null,
        ?int $createdBy = null
    ): self {
        return self::create([
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'category' => $category,
            'action_url' => $actionUrl,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }
}
