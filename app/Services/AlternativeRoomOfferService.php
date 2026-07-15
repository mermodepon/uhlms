<?php

namespace App\Services;

use App\Mail\AlternativeRoomOfferMail;
use App\Mail\SendPaymentLinkMail;
use App\Models\Reservation;
use App\Models\ReservationAlternativeOffer;
use App\Models\ReservationCharge;
use App\Models\ReservationLog;
use App\Models\ReservationRoomRequest;
use App\Models\Room;
use App\Models\RoomHold;
use App\Models\RoomType;
use App\Support\CanonicalAppUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AlternativeRoomOfferService
{
    public function propose(Reservation $reservation, array $data): ReservationAlternativeOffer
    {
        if ($reservation->status !== 'pending') {
            throw new \RuntimeException('Only pending reservations can receive an alternative room offer.');
        }

        $requestLine = $reservation->roomRequests()->findOrFail((int) $data['reservation_room_request_id']);
        $roomType = RoomType::findOrFail((int) $data['offered_room_type_id']);
        $roomIds = array_values(array_unique(array_filter(array_map('intval', $data['room_ids'] ?? []))));
        $checkIn = Carbon::parse($reservation->check_in_date);
        $checkOut = Carbon::parse($reservation->check_out_date);

        $requestedAvailability = app(RoomHoldService::class)->getDateAvailabilitySummary(
            $requestLine->roomType,
            $checkIn,
            $checkOut,
            (int) $requestLine->occupant_count,
            $requestLine->requested_capacity,
        );
        if ($requestedAvailability['available_rooms_count'] >= (int) $requestLine->requested_room_count
            && $requestedAvailability['can_accommodate_requested_guests']) {
            throw new \RuntimeException('The requested room type is available. Use the normal approval action instead.');
        }

        if (count($roomIds) !== (int) $requestLine->requested_room_count) {
            throw new \RuntimeException('Select exactly the number of rooms requested for this stay.');
        }

        $rooms = Room::query()
            ->whereIn('id', $roomIds)
            ->where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->whereNotIn('status', ['maintenance', 'inactive'])
            ->with('roomType')
            ->get();

        if ($rooms->count() !== count($roomIds)) {
            throw new \RuntimeException('One or more selected rooms are not valid for the alternative room type.');
        }

        $allocation = $this->allocateGuests($rooms, (int) $requestLine->occupant_count, $roomType->isPrivate());
        foreach ($rooms as $room) {
            if (app(RoomHoldService::class)->hasConflict($room, $checkIn, $checkOut, $allocation[$room->id])) {
                throw new \RuntimeException("Room {$room->room_number} is no longer available for these dates.");
            }
        }

        $nights = max(1, $checkIn->diffInDays($checkOut));
        $originalTotal = round($nights * (float) $requestLine->roomType->base_rate * (int) $requestLine->requested_room_count, 2);
        $quotedTotal = round($nights * (float) $roomType->base_rate * count($rooms), 2);
        $expiresAt = now()->addHours(24);

        return DB::transaction(function () use ($reservation, $requestLine, $roomType, $rooms, $allocation, $originalTotal, $quotedTotal, $expiresAt, $data) {
            $this->expireOpenOffers($reservation);

            $offer = ReservationAlternativeOffer::create([
                'reservation_id' => $reservation->id,
                'reservation_room_request_id' => $requestLine->id,
                'offered_room_type_id' => $roomType->id,
                'room_ids' => $rooms->pluck('id')->values()->all(),
                'original_total' => $originalTotal,
                'quoted_total' => $quotedTotal,
                'message' => $data['message'] ?? null,
                'status' => ReservationAlternativeOffer::STATUS_PENDING,
                'expires_at' => $expiresAt,
                'proposed_by' => auth()->id(),
            ]);

            foreach ($rooms as $room) {
                RoomHold::create([
                    'room_id' => $room->id,
                    'reservation_id' => $reservation->id,
                    'hold_from' => $reservation->check_in_date,
                    'hold_to' => $reservation->check_out_date,
                    'hold_type' => 'short_term',
                    'held_guest_count' => $roomType->isPrivate() ? null : $allocation[$room->id],
                    'expires_at' => $expiresAt,
                ]);
            }

            $reservation->update(['status' => 'awaiting_alternative_confirmation']);
            ReservationLog::record($reservation, 'alternative_offer_sent', 'Alternative room offer sent to guest.', [
                'offer_id' => $offer->id,
                'room_type' => $roomType->name,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $offer->fresh(['reservation', 'requestLine.roomType', 'offeredRoomType']);
        });
    }

    public function sendOfferEmail(ReservationAlternativeOffer $offer): void
    {
        $relativeUrl = URL::temporarySignedRoute('guest.alternative-offers.show', $offer->expires_at, ['offer' => $offer->id], false);
        $offerUrl = CanonicalAppUrl::fromRelative($relativeUrl);
        Mail::to($offer->reservation->guest_email)->send(new AlternativeRoomOfferMail($offer, $offerUrl));
    }

    public function accept(ReservationAlternativeOffer $offer): Reservation
    {
        if (! $offer->isPending()) {
            throw new \RuntimeException('This alternative room offer is no longer available.');
        }

        return DB::transaction(function () use ($offer) {
            $reservation = $offer->reservation()->lockForUpdate()->firstOrFail();
            $holds = $reservation->roomHolds()
                ->where('hold_type', 'short_term')
                ->whereIn('room_id', $offer->room_ids)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->get();

            if ($holds->count() !== count($offer->room_ids)) {
                throw new \RuntimeException('The temporary room hold has expired.');
            }

            $holds->each->update(['hold_type' => 'advance', 'expires_at' => null]);
            $offer->update(['status' => ReservationAlternativeOffer::STATUS_ACCEPTED, 'responded_at' => now()]);

            $reservation->charges()
                ->where('scope_type', 'alternative_offer')
                ->where('scope_id', $offer->id)
                ->delete();
            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'room_rate',
                'scope_type' => 'alternative_offer',
                'scope_id' => $offer->id,
                'description' => 'Accepted alternative: '.$offer->offeredRoomType->name,
                'qty' => 1,
                'unit_price' => $offer->quoted_total,
                'amount' => $offer->quoted_total,
                'currency' => 'PHP',
                'meta' => ['offer_id' => $offer->id, 'original_total' => $offer->original_total],
            ]);

            $reservation->update(['status' => 'confirmed', 'approved_at' => now(), 'reviewed_at' => now()]);
            $reservation->refreshFinancialSummary();
            $reservation->issueGuestPaymentLink(rotateToken: true);
            $reservation->save();

            ReservationLog::record($reservation, 'alternative_offer_accepted', 'Guest accepted the alternative room offer.', ['offer_id' => $offer->id]);

            return $reservation->fresh();
        });
    }

    public function decline(ReservationAlternativeOffer $offer, string $reason = 'declined'): void
    {
        if (! in_array($offer->status, [ReservationAlternativeOffer::STATUS_PENDING, ReservationAlternativeOffer::STATUS_EXPIRED], true)) {
            return;
        }

        DB::transaction(function () use ($offer, $reason) {
            $reservation = $offer->reservation;
            $reservation->roomHolds()->where('hold_type', 'short_term')->whereIn('room_id', $offer->room_ids)->delete();
            $offer->update([
                'status' => $reason === 'expired' ? ReservationAlternativeOffer::STATUS_EXPIRED : ReservationAlternativeOffer::STATUS_DECLINED,
                'responded_at' => now(),
            ]);
            $reservation->update(['status' => 'pending']);
            ReservationLog::record($reservation, 'alternative_offer_'.$reason, 'Alternative room offer '.$reason.'.', ['offer_id' => $offer->id]);
        });
    }

    public function expireIfNeeded(ReservationAlternativeOffer $offer): void
    {
        if ($offer->status === ReservationAlternativeOffer::STATUS_PENDING && $offer->expires_at->isPast()) {
            $this->decline($offer, 'expired');
        }
    }

    private function expireOpenOffers(Reservation $reservation): void
    {
        $reservation->alternativeOffers()->where('status', ReservationAlternativeOffer::STATUS_PENDING)->get()
            ->each(fn (ReservationAlternativeOffer $offer) => $this->decline($offer, 'expired'));
    }

    private function allocateGuests($rooms, int $guestCount, bool $isPrivate): array
    {
        if ($isPrivate) return $rooms->mapWithKeys(fn (Room $room) => [$room->id => null])->all();

        $remaining = max(1, $guestCount);
        $allocation = [];
        foreach ($rooms as $room) {
            $allocated = min(max(0, (int) $room->capacity), $remaining);
            if ($allocated < 1) throw new \RuntimeException("Room {$room->room_number} has no guest capacity.");
            $allocation[$room->id] = $allocated;
            $remaining -= $allocated;
        }
        if ($remaining > 0) throw new \RuntimeException('The selected alternative rooms cannot accommodate all guests.');
        return $allocation;
    }
}
