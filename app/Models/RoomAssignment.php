<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAssignment extends Model
{
    protected $fillable = [
        'reservation_id',
        'guest_id',
        'room_id',
        'assigned_by',
        'assigned_at',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'checked_out_by',
        'status',
        'notes',
        'remarks',
        // Detailed guest information
        'guest_last_name',
        'guest_first_name',
        'guest_middle_initial',
        'guest_gender',
        'guest_age',
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
        'or_date',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'status' => 'string',
            'detailed_checkin_datetime' => 'datetime',
            'detailed_checkout_datetime' => 'datetime',
            'is_student' => 'boolean',
            'is_senior_citizen' => 'boolean',
            'is_pwd' => 'boolean',
            'guest_age' => 'integer',
            'additional_requests' => 'array',
            'payment_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // No bed-derived room_id logic needed — room_id must always be set explicitly
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function checkedOutByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
