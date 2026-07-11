<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function markReviewed(?User $user = null): void
    {
        $this->forceFill([
            'status' => 'reviewed',
            'reviewed_by' => $user?->id,
            'reviewed_at' => now(),
        ])->save();
    }
}
