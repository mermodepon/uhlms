<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationLog;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomHold;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InStayExtensionService
{
    /** @return \Illuminate\Support\Collection<int, ReservationCharge> */
    public function extend(Reservation $reservation, mixed $newCheckoutDate, ?string $note = null)
    {
        $this->ensureCheckedIn($reservation);
        $newCheckout = Carbon::parse($newCheckoutDate)->startOfDay();

        return DB::transaction(function () use ($reservation, $newCheckout, $note) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $oldCheckout = Carbon::parse($reservation->check_out_date)->startOfDay();
            if ($newCheckout->lte($oldCheckout)) {
                throw new \RuntimeException('The new check-out date must be later than the current check-out date.');
            }

            $assignments = $reservation->roomAssignments()->where('status', 'checked_in')->lockForUpdate()->get();
            if ($assignments->isEmpty()) {
                throw new \RuntimeException('A checked-in reservation must have an active room assignment to be extended.');
            }

            $rooms = Room::query()->with('roomType')->whereIn('id', $assignments->pluck('room_id')->unique())->lockForUpdate()->get()->keyBy('id');
            if ($rooms->count() !== $assignments->pluck('room_id')->unique()->count()) {
                throw new \RuntimeException('One or more assigned rooms no longer exist.');
            }
            foreach ($rooms as $room) {
                if (! $room->is_active || in_array($room->status, ['maintenance', 'inactive'], true)) {
                    throw new \RuntimeException("Room {$room->room_number} is not available for an extension.");
                }
                $this->ensureRoomAvailableForExtension($reservation, $room, $assignments->where('room_id', $room->id), $oldCheckout, $newCheckout);
            }

            $discount = $this->eligibleDiscount($assignments);
            $charges = collect();
            $extensionId = (string) Str::uuid();
            $addedNights = $oldCheckout->diffInDays($newCheckout);
            foreach ($rooms as $room) {
                $roomAssignments = $assignments->where('room_id', $room->id);
                $rate = (float) ($room->roomType?->base_rate ?? 0);
                $perPerson = $room->roomType?->pricing_type === 'per_person';
                $occupants = max(1, $roomAssignments->count());
                $nightlyAmount = $rate * ($perPerson ? $occupants : 1);
                $amount = round($nightlyAmount * $addedNights, 2);
                $charge = ReservationCharge::create([
                    'reservation_id' => $reservation->id,
                    'charge_type' => 'room_rate',
                    'scope_type' => 'reservation',
                    'scope_id' => $reservation->id,
                    'description' => 'In-stay extension: '.$room->roomType?->name.' #'.$room->room_number.' ('.$addedNights.' night'.($addedNights === 1 ? '' : 's').')',
                    'qty' => $addedNights,
                    'unit_price' => $nightlyAmount,
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'meta' => [
                        'source' => 'in_stay_extension',
                        'extension_id' => $extensionId,
                        'room_id' => $room->id,
                        'room_number' => $room->room_number,
                        'room_type' => $room->roomType?->name,
                        'pricing_type' => $room->roomType?->pricing_type,
                        'rate_snapshot' => $rate,
                        'occupants_snapshot' => $occupants,
                        'original_checkout_date' => $oldCheckout->toDateString(),
                        'new_checkout_date' => $newCheckout->toDateString(),
                        'added_nights' => $addedNights,
                        'note' => filled($note) ? trim($note) : null,
                    ],
                    'created_by' => auth()->id(),
                ]);
                $charges->push($charge);
                if ($discount['percent'] > 0 && $amount > 0) {
                    $discountAmount = round($amount * $discount['percent'] / 100, 2);
                    ReservationCharge::create([
                        'reservation_id' => $reservation->id, 'charge_type' => 'discount', 'scope_type' => 'reservation', 'scope_id' => $reservation->id,
                        'description' => 'Stay extension discount: '.$discount['label'], 'qty' => 1, 'unit_price' => -$discountAmount, 'amount' => -$discountAmount, 'currency' => 'PHP',
                        'meta' => ['source' => 'in_stay_extension_discount', 'applies_to_charge_id' => $charge->id, 'discount_percent' => $discount['percent'], 'discount_label' => $discount['label']],
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $reservation->update(['check_out_date' => $newCheckout->toDateString()]);
            $assignments->each->update(['detailed_checkout_datetime' => $newCheckout->toDateString()]);
            $reservation->roomHolds()->where('hold_type', 'advance')->update(['hold_to' => $newCheckout->toDateString()]);
            $reservation->refreshFinancialSummary();
            ReservationLog::record($reservation, 'in_stay_extension_posted', 'In-stay extension posted for '.$addedNights.' night'.($addedNights === 1 ? '' : 's').'.', [
                'extension_id' => $extensionId, 'charge_ids' => $charges->pluck('id')->all(), 'room_ids' => $rooms->keys()->all(), 'original_checkout_date' => $oldCheckout->toDateString(), 'new_checkout_date' => $newCheckout->toDateString(), 'added_nights' => $addedNights, 'note' => filled($note) ? trim($note) : null,
            ]);

            return $charges;
        });
    }

    public function void(Reservation $reservation, ReservationCharge $charge, string $reason): void
    {
        $this->ensureCheckedIn($reservation);
        if ($charge->reservation_id !== $reservation->id || data_get($charge->meta, 'source') !== 'in_stay_extension') throw new \RuntimeException('Only posted in-stay extensions can be voided.');
        if (blank($reason)) throw new \RuntimeException('A reason is required to void an extension.');

        DB::transaction(function () use ($reservation, $charge, $reason): void {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $charge = $reservation->charges()->lockForUpdate()->findOrFail($charge->id);
            $oldCheckout = Carbon::parse(data_get($charge->meta, 'original_checkout_date'))->startOfDay();
            $newCheckout = Carbon::parse(data_get($charge->meta, 'new_checkout_date'))->startOfDay();
            if (Carbon::today()->gte($oldCheckout)) throw new \RuntimeException('An extension cannot be voided after its extension period has begun.');
            if (Carbon::parse($reservation->check_out_date)->startOfDay()->ne($newCheckout)) throw new \RuntimeException('Only the most recent active stay extension can be voided.');
            $extensionId = data_get($charge->meta, 'extension_id');
            $extensionCharges = filled($extensionId)
                ? $reservation->charges()->where('charge_type', 'room_rate')->get()->filter(fn (ReservationCharge $candidate) => data_get($candidate->meta, 'extension_id') === $extensionId)
                : collect([$charge]);
            if ($extensionCharges->isEmpty() || $reservation->charges()->get()->contains(fn (ReservationCharge $candidate) => $extensionCharges->contains('id', (int) data_get($candidate->meta, 'voids_charge_id', 0)))) throw new \RuntimeException('This stay extension has already been voided.');

            foreach ($extensionCharges as $extensionCharge) {
                ReservationCharge::create(['reservation_id' => $reservation->id, 'charge_type' => 'room_rate', 'scope_type' => 'reservation', 'scope_id' => $reservation->id, 'description' => 'Void: '.$extensionCharge->description, 'qty' => $extensionCharge->qty, 'unit_price' => -(float) $extensionCharge->unit_price, 'amount' => -(float) $extensionCharge->amount, 'currency' => $extensionCharge->currency, 'meta' => ['source' => 'in_stay_extension_void', 'voids_charge_id' => $extensionCharge->id, 'reason' => trim($reason)], 'created_by' => auth()->id()]);
                $reservation->charges()->where('charge_type', 'discount')->get()->filter(fn (ReservationCharge $discount) => (int) data_get($discount->meta, 'applies_to_charge_id', 0) === $extensionCharge->id)->each(function (ReservationCharge $discount) use ($reservation, $extensionCharge, $reason): void {
                    ReservationCharge::create(['reservation_id' => $reservation->id, 'charge_type' => 'discount', 'scope_type' => 'reservation', 'scope_id' => $reservation->id, 'description' => 'Reversal: '.$discount->description, 'qty' => 1, 'unit_price' => abs((float) $discount->unit_price), 'amount' => abs((float) $discount->amount), 'currency' => $discount->currency, 'meta' => ['source' => 'in_stay_extension_discount_void', 'voids_charge_id' => $discount->id, 'voids_extension_charge_id' => $extensionCharge->id, 'reason' => trim($reason)], 'created_by' => auth()->id()]);
                });
            }
            $reservation->update(['check_out_date' => $oldCheckout->toDateString()]);
            $reservation->roomAssignments()->where('status', 'checked_in')->update(['detailed_checkout_datetime' => $oldCheckout->toDateString()]);
            $reservation->roomHolds()->where('hold_type', 'advance')->update(['hold_to' => $oldCheckout->toDateString()]);
            $reservation->refreshFinancialSummary();
            ReservationLog::record($reservation, 'in_stay_extension_voided', 'In-stay extension voided: '.trim($reason), ['charge_id' => $charge->id, 'reason' => trim($reason)]);
        });
    }

    private function ensureRoomAvailableForExtension(Reservation $reservation, Room $room, $ownAssignments, Carbon $from, Carbon $to): void
    {
        $otherHolds = RoomHold::query()->where('room_id', $room->id)->where('reservation_id', '!=', $reservation->id)->active()->conflictingWith($from, $to)->get();
        $otherAssignments = RoomAssignment::query()->where('room_id', $room->id)->where('reservation_id', '!=', $reservation->id)->whereNull('checked_out_at')->whereHas('reservation', fn ($q) => $q->where('check_in_date', '<', $to)->where('check_out_date', '>', $from))->count();
        if ($room->roomType?->isPrivate()) {
            if ($otherHolds->isNotEmpty() || $otherAssignments > 0) throw new \RuntimeException("Room {$room->room_number} is unavailable for the requested extension.");
            return;
        }
        $held = $otherHolds->sum(fn (RoomHold $hold) => $hold->held_guest_count ?? max(1, (int) $hold->reservation?->number_of_occupants));
        if ($held + $otherAssignments + $ownAssignments->count() > max(0, (int) $room->capacity)) throw new \RuntimeException("Room {$room->room_number} lacks capacity for the requested extension.");
    }

    /** @return array{percent:float,label:string} */
    private function eligibleDiscount($assignments): array
    {
        $candidates = [];
        if ($assignments->contains(fn ($a) => $a->is_pwd) && ($p = (float) Setting::get('discount_pwd_percent', 0)) > 0) $candidates[] = ['percent' => $p, 'label' => "PWD ({$p}%)"];
        if ($assignments->contains(fn ($a) => $a->is_senior_citizen) && ($p = (float) Setting::get('discount_senior_percent', 0)) > 0) $candidates[] = ['percent' => $p, 'label' => "Senior Citizen ({$p}%)"];
        if ($assignments->contains(fn ($a) => $a->is_student) && ($p = (float) Setting::get('discount_student_percent', 0)) > 0) $candidates[] = ['percent' => $p, 'label' => "Student ({$p}%)"];
        usort($candidates, fn ($a, $b) => $b['percent'] <=> $a['percent']);
        return $candidates[0] ?? ['percent' => 0.0, 'label' => ''];
    }

    private function ensureCheckedIn(Reservation $reservation): void
    {
        if ($reservation->status !== 'checked_in') throw new \RuntimeException('Stay extensions can only be posted or voided while the reservation is checked in.');
    }
}
