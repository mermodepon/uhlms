<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssignment extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_id',
        'assigned_by',
        'assigned_at',
        'notes',
        // Detailed guest information
        'guest_last_name',
        'guest_first_name',
        'guest_middle_initial',
        'guest_full_address',
        'guest_contact_number',
        'id_type',
        'id_number',
        'is_student',
        'is_senior_citizen',
        'is_pwd',
        'purpose_of_stay',
        'nationality',
        'num_male_guests',
        'num_female_guests',
        'detailed_checkin_datetime',
        'detailed_checkout_datetime',
        'additional_requests',
        'payment_mode',
        'payment_mode_other',
        'payment_amount',
        'payment_or_number',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'detailed_checkin_datetime' => 'datetime',
            'detailed_checkout_datetime' => 'datetime',
            'is_student' => 'boolean',
            'is_senior_citizen' => 'boolean',
            'is_pwd' => 'boolean',
            'additional_requests' => 'array',
            'payment_amount' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
