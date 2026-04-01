<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationLog extends Model
{
    protected $fillable = [
        'reservation_id',
        'event',
        'description',
        'actor_id',
        'actor_name',
        'meta',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'logged_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Write a log entry for a reservation.
     *
     * @param  string  $event  Machine-readable key, e.g. 'checkin_finalized'
     * @param  string  $description  Human-readable summary
     * @param  array<string,mixed>  $meta  Optional extra context
     * @param  int|null  $actorId  Defaults to the authenticated user
     * @param  string|null  $actorName  Defaults to the authenticated user's name
     */
    public static function record(
        Reservation|int $reservation,
        string $event,
        string $description,
        array $meta = [],
        ?int $actorId = null,
        ?string $actorName = null,
    ): static {
        $reservationId = $reservation instanceof Reservation ? $reservation->id : $reservation;

        if ($actorId === null) {
            $actorId = auth()->id();
        }

        if ($actorName === null) {
            $actorName = auth()->user()?->name
                ?? ($actorId ? User::find($actorId)?->name : null);
        }

        return static::create([
            'reservation_id' => $reservationId,
            'event' => $event,
            'description' => $description,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'meta' => empty($meta) ? null : $meta,
            'logged_at' => now(),
        ]);
    }

    /**
     * Human-friendly label for a given event key.
     */
    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'reservation_created' => 'Reservation Created',
            'reservation_approved' => 'Approved',
            'reservation_declined' => 'Declined',
            'reservation_cancelled' => 'Cancelled',
            'reservation_checked_out' => 'Checked Out',
            'checkin_hold_prepared' => 'Hold Prepared',
            'checkin_hold_released' => 'Hold Released',
            'checkin_hold_expired' => 'Hold Expired',
            'checkin_finalized' => 'Check-in Finalized',
            'guest_checked_in' => 'Guest Checked In',
            'guest_checked_out' => 'Guest Checked Out',
            'room_assignment_removed' => 'Assignment Removed',
            default => ucwords(str_replace('_', ' ', $event)),
        };
    }

    /**
     * Filament badge color for a given event key.
     */
    public static function eventColor(string $event): string
    {
        return match ($event) {
            'reservation_created' => 'info',
            'reservation_approved' => 'success',
            'reservation_declined',
            'reservation_cancelled' => 'danger',
            'reservation_checked_out' => 'gray',
            'checkin_hold_prepared' => 'warning',
            'checkin_hold_released' => 'gray',
            'checkin_hold_expired' => 'warning',
            'checkin_finalized' => 'success',
            'guest_checked_in' => 'info',
            'guest_checked_out' => 'gray',
            'room_assignment_removed' => 'warning',
            default => 'gray',
        };
    }
}
