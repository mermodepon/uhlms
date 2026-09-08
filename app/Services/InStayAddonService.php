<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationLog;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InStayAddonService
{
    /**
     * Post immutable add-on charges for a guest who is currently checked in.
     *
     * @param  array<int, array{code:string,qty:int|float}>  $items
     * @return Collection<int, ReservationCharge>
     */
    public function post(Reservation $reservation, array $items, ?string $note = null): Collection
    {
        $this->ensureCheckedIn($reservation);

        $items = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['code'] ?? null))
            ->map(fn (array $item): array => [
                'code' => (string) $item['code'],
                'qty' => max(1, (int) ($item['qty'] ?? 1)),
            ])
            ->values();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Select at least one add-on to post.');
        }

        return DB::transaction(function () use ($reservation, $items, $note): Collection {
            $services = Service::active()
                ->whereIn('code', $items->pluck('code')->unique())
                ->get(['code', 'name', 'price'])
                ->keyBy('code');

            if ($services->count() !== $items->pluck('code')->unique()->count()) {
                throw new \RuntimeException('One or more selected add-ons are no longer active.');
            }

            $discount = $this->eligibleDiscount($reservation);
            $charges = collect();

            foreach ($items as $item) {
                $service = $services->get($item['code']);
                $qty = $item['qty'];
                $unitPrice = (float) $service->price;
                $amount = round($unitPrice * $qty, 2);

                $charge = ReservationCharge::create([
                    'reservation_id' => $reservation->id,
                    'charge_type' => 'addon',
                    'scope_type' => 'reservation',
                    'scope_id' => $reservation->id,
                    'description' => ($qty > 1 ? "{$qty}x " : '').$service->name,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'meta' => [
                        'source' => 'in_stay_addon',
                        'service_code' => $service->code,
                        'note' => filled($note) ? trim($note) : null,
                    ],
                    'created_by' => auth()->id(),
                ]);

                $charges->push($charge);

                if ($discount['percent'] > 0 && $amount > 0) {
                    $discountAmount = round($amount * $discount['percent'] / 100, 2);
                    ReservationCharge::create([
                        'reservation_id' => $reservation->id,
                        'charge_type' => 'discount',
                        'scope_type' => 'reservation',
                        'scope_id' => $reservation->id,
                        'description' => 'Add-on discount: '.$discount['label'],
                        'qty' => 1,
                        'unit_price' => -$discountAmount,
                        'amount' => -$discountAmount,
                        'currency' => 'PHP',
                        'meta' => [
                            'source' => 'in_stay_addon_discount',
                            'applies_to_charge_id' => $charge->id,
                            'discount_percent' => $discount['percent'],
                            'discount_label' => $discount['label'],
                        ],
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $reservation->refreshFinancialSummary();
            $total = (float) $charges->sum('amount');
            ReservationLog::record(
                $reservation,
                'in_stay_addon_posted',
                'Posted '.count($charges).' in-stay add-on(s) totalling PHP '.number_format($total, 2).'.',
                ['charge_ids' => $charges->pluck('id')->all(), 'note' => filled($note) ? trim($note) : null]
            );

            return $charges;
        });
    }

    public function void(Reservation $reservation, ReservationCharge $charge, string $reason): void
    {
        $this->ensureCheckedIn($reservation);

        if ($charge->reservation_id !== $reservation->id || $charge->charge_type !== 'addon' || data_get($charge->meta, 'source') !== 'in_stay_addon') {
            throw new \RuntimeException('Only posted in-stay add-ons can be voided.');
        }

        if (blank($reason)) {
            throw new \RuntimeException('A reason is required to void an add-on.');
        }

        DB::transaction(function () use ($reservation, $charge, $reason): void {
            $alreadyVoided = $reservation->charges()
                ->where('charge_type', 'addon')
                ->get()
                ->contains(fn (ReservationCharge $candidate): bool => (int) data_get($candidate->meta, 'voids_charge_id', 0) === $charge->id);

            if ($alreadyVoided) {
                throw new \RuntimeException('This add-on has already been voided.');
            }

            ReservationCharge::create([
                'reservation_id' => $reservation->id,
                'charge_type' => 'addon',
                'scope_type' => 'reservation',
                'scope_id' => $reservation->id,
                'description' => 'Void: '.$charge->description,
                'qty' => $charge->qty,
                'unit_price' => -(float) $charge->unit_price,
                'amount' => -(float) $charge->amount,
                'currency' => $charge->currency,
                'meta' => [
                    'source' => 'in_stay_addon_void',
                    'voids_charge_id' => $charge->id,
                    'reason' => trim($reason),
                ],
                'created_by' => auth()->id(),
            ]);

            $reservation->charges()
                ->where('charge_type', 'discount')
                ->get()
                ->filter(fn (ReservationCharge $discount): bool => (int) data_get($discount->meta, 'applies_to_charge_id', 0) === $charge->id)
                ->each(function (ReservationCharge $discount) use ($reservation, $charge, $reason): void {
                    ReservationCharge::create([
                        'reservation_id' => $reservation->id,
                        'charge_type' => 'discount',
                        'scope_type' => 'reservation',
                        'scope_id' => $reservation->id,
                        'description' => 'Reversal: '.$discount->description,
                        'qty' => 1,
                        'unit_price' => abs((float) $discount->unit_price),
                        'amount' => abs((float) $discount->amount),
                        'currency' => $discount->currency,
                        'meta' => [
                            'source' => 'in_stay_addon_discount_void',
                            'voids_charge_id' => $discount->id,
                            'voids_addon_charge_id' => $charge->id,
                            'reason' => trim($reason),
                        ],
                        'created_by' => auth()->id(),
                    ]);
                });

            $reservation->refreshFinancialSummary();
            ReservationLog::record(
                $reservation,
                'in_stay_addon_voided',
                'Voided in-stay add-on "'.$charge->description.'": '.trim($reason),
                ['charge_id' => $charge->id, 'reason' => trim($reason)]
            );
        });
    }

    /** @return array{percent:float,label:string} */
    private function eligibleDiscount(Reservation $reservation): array
    {
        $assignment = $reservation->roomAssignments()->latest('checked_in_at')->first();
        $candidates = [];

        if ($assignment?->is_pwd && ($percent = (float) Setting::get('discount_pwd_percent', 0)) > 0) {
            $candidates[] = ['percent' => $percent, 'label' => "PWD ({$percent}%)"];
        }
        if ($assignment?->is_senior_citizen && ($percent = (float) Setting::get('discount_senior_percent', 0)) > 0) {
            $candidates[] = ['percent' => $percent, 'label' => "Senior Citizen ({$percent}%)"];
        }
        if ($assignment?->is_student && ($percent = (float) Setting::get('discount_student_percent', 0)) > 0) {
            $candidates[] = ['percent' => $percent, 'label' => "Student ({$percent}%)"];
        }

        if (empty($candidates)) {
            return ['percent' => 0.0, 'label' => ''];
        }

        usort($candidates, fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        return $candidates[0];
    }

    private function ensureCheckedIn(Reservation $reservation): void
    {
        if ($reservation->status !== 'checked_in') {
            throw new \RuntimeException('Add-ons can only be posted or voided while the reservation is checked in.');
        }
    }
}
