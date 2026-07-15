<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ReservationFeedback extends Model
{
    protected $table = 'reservation_feedback';

    protected $fillable = [
        'reservation_id',
        'guest_account_id',
        'overall_rating',
        'cleanliness_rating',
        'comfort_rating',
        'service_rating',
        'value_rating',
        'booking_experience_rating',
        'would_stay_again',
        'comments',
        'admin_notes',
        'status',
        'visibility_status',
        'public_display_consent',
        'public_display_room_type',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'cleanliness_rating' => 'integer',
            'comfort_rating' => 'integer',
            'service_rating' => 'integer',
            'value_rating' => 'integer',
            'booking_experience_rating' => 'integer',
            'would_stay_again' => 'boolean',
            'public_display_consent' => 'boolean',
            'public_display_room_type' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guestAccount(): BelongsTo
    {
        return $this->belongsTo(GuestAccount::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected static function booted(): void
    {
        static::saving(function (self $feedback): void {
            $canBePublic = $feedback->public_display_consent
                && $feedback->status === 'reviewed'
                && filled($feedback->comments);

            if (! $canBePublic) {
                $feedback->visibility_status = 'internal';
            }

            if (! $feedback->public_display_consent) {
                $feedback->public_display_room_type = false;
            }
        });
    }

    public function scopePublicTestimonials(Builder $query): Builder
    {
        return $query
            ->where('status', 'reviewed')
            ->where('visibility_status', 'public')
            ->where('public_display_consent', true)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('comments')
            ->where('comments', '!=', '');
    }

    public function publicGuestName(): string
    {
        return 'Verified guest';
    }

    public function publicRoomTypeLabel(): ?string
    {
        if (! $this->public_display_room_type || ! $this->relationLoaded('reservation')) {
            return null;
        }

        $roomTypes = $this->reservation?->roomAssignments
            ->pluck('room.roomType')
            ->filter()
            ->unique('id')
            ->values();

        return $roomTypes?->count() === 1 ? $roomTypes->first()->name : null;
    }

    public function markReviewed(?User $user = null): void
    {
        $this->forceFill([
            'status' => 'reviewed',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
