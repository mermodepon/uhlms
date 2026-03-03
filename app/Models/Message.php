<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'reservation_id',
        'sender_id',
        'sender_name',
        'sender_email',
        'subject',
        'sender_type',
        'message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function getSenderDisplayNameAttribute(): string
    {
        if ($this->sender) {
            return $this->sender->name . ' (' . ucfirst($this->sender_type) . ')';
        }
        
        return $this->sender_name ?? 'Guest';
    }
}
