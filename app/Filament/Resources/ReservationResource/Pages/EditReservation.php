<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\ReservationCharge;
use App\Models\ReservationPayment;
use App\Models\Service;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditReservation extends EditRecord
{
    protected static string $resource = ReservationResource::class;

    protected array $checkinFieldsToUpdate = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (in_array($this->record->status, ['checked_out', 'cancelled', 'declined'])) {
            Notification::make()
                ->title('Reservation is read-only')
                ->body('Checked-out, cancelled, and declined reservations cannot be edited.')
                ->warning()
                ->send();

            $this->redirect(ReservationResource::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load check-in data into form fields with checkin_ prefix
        if (in_array($this->record->status, ['checked_in', 'checked_out'], true)) {
            $snapshot = $this->record->checkInSnapshots()->latest('id')->first();
            $latestPayment = $this->record->payments()->where('status', 'posted')->latest('id')->first();
            $latestAssignment = $this->record->roomAssignments()->latest('id')->first();

            // ID & Personal Info
            $data['checkin_id_type'] = $snapshot?->id_type ?? $latestAssignment?->id_type;
            $data['checkin_id_number'] = $snapshot?->id_number ?? $latestAssignment?->id_number;
            $data['checkin_nationality'] = $snapshot?->nationality ?? $latestAssignment?->nationality ?? 'Filipino';
            $data['checkin_purpose_of_stay'] = $snapshot?->purpose_of_stay ?? $latestAssignment?->purpose_of_stay;
            $data['checkin_is_student'] = (bool) ($latestAssignment?->is_student ?? false);
            $data['checkin_is_senior_citizen'] = (bool) ($latestAssignment?->is_senior_citizen ?? false);
            $data['checkin_is_pwd'] = (bool) ($latestAssignment?->is_pwd ?? false);

            // Schedule
            $data['checkin_detailed_checkin_datetime'] = $snapshot?->detailed_checkin_datetime ?? $latestAssignment?->detailed_checkin_datetime;
            $data['checkin_detailed_checkout_datetime'] = $snapshot?->detailed_checkout_datetime ?? $latestAssignment?->detailed_checkout_datetime;

            // Add-ons - check ledger first, then assignment
            $charges = $this->record->charges()->where('charge_type', 'addon')->get();
            if ($charges->isNotEmpty()) {
                $addonItems = $charges->map(fn ($charge) => [
                    'code' => data_get($charge->meta, 'service_code'),
                    'qty' => (int) max(1, $charge->qty ?? 1),
                ])->filter(fn ($i) => ! empty($i['code']))->values()->all();
                $data['checkin_additional_requests'] = $addonItems;
            } else {
                $raw = $latestAssignment?->additional_requests ?? [];
                // Normalize legacy format (array of plain code strings)
                if (is_array($raw) && ! empty($raw) && isset($raw[0]) && is_string($raw[0])) {
                    $raw = array_map(fn ($code) => ['code' => $code, 'qty' => 1], $raw);
                }
                $data['checkin_additional_requests'] = $raw;
            }

            // Payment Info
            $data['checkin_payment_mode'] = $latestPayment?->payment_mode ?? $latestAssignment?->payment_mode;
            $data['checkin_payment_mode_other'] = $latestAssignment?->payment_mode_other;
            $data['checkin_payment_amount'] = $latestPayment?->amount ?? $latestAssignment?->payment_amount;
            $data['checkin_payment_or_number'] = $latestPayment?->reference_no ?? $latestAssignment?->payment_or_number;
            $data['checkin_or_date'] = $latestPayment?->or_date ?? $latestAssignment?->or_date;
            $data['checkin_remarks'] = $snapshot?->remarks ?? $latestAssignment?->remarks;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract check-in fields (prefixed with checkin_) and save them separately
        $checkinFields = [];
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'checkin_')) {
                $realKey = substr($key, 8); // Remove 'checkin_' prefix
                $checkinFields[$realKey] = $value;
                unset($data[$key]);
            }
        }

        // Store check-in fields for processing in afterSave
        $this->checkinFieldsToUpdate = $checkinFields;

        return $data;
    }

    protected function afterSave(): void
    {
        if (empty($this->checkinFieldsToUpdate ?? [])) {
            return;
        }

        $record = $this->record;
        $fields = $this->checkinFieldsToUpdate;

        if (! in_array($record->status, ['checked_in', 'checked_out'], true)) {
            return;
        }

        DB::transaction(function () use ($record, $fields) {
            // Update all room assignments with the new check-in fields
            $assignmentUpdates = [];
            $fieldMapping = [
                'id_type' => 'id_type',
                'id_number' => 'id_number',
                'nationality' => 'nationality',
                'purpose_of_stay' => 'purpose_of_stay',
                'is_student' => 'is_student',
                'is_senior_citizen' => 'is_senior_citizen',
                'is_pwd' => 'is_pwd',
                'detailed_checkin_datetime' => 'detailed_checkin_datetime',
                'detailed_checkout_datetime' => 'detailed_checkout_datetime',
                'additional_requests' => 'additional_requests',
                'payment_mode' => 'payment_mode',
                'payment_mode_other' => 'payment_mode_other',
                'payment_amount' => 'payment_amount',
                'payment_or_number' => 'payment_or_number',
                'or_date' => 'or_date',
                'remarks' => 'remarks',
            ];

            foreach ($fieldMapping as $formField => $dbField) {
                if (array_key_exists($formField, $fields)) {
                    $assignmentUpdates[$dbField] = $fields[$formField];
                }
            }

            if (! empty($assignmentUpdates)) {
                $record->roomAssignments()->update($assignmentUpdates);
            }

            // Update check-in snapshot if exists
            $snapshot = $record->checkInSnapshots()->latest('id')->first();
            if ($snapshot) {
                $snapshotUpdates = [];
                $snapshotMapping = [
                    'id_type' => 'id_type',
                    'id_number' => 'id_number',
                    'nationality' => 'nationality',
                    'purpose_of_stay' => 'purpose_of_stay',
                    'detailed_checkin_datetime' => 'detailed_checkin_datetime',
                    'detailed_checkout_datetime' => 'detailed_checkout_datetime',
                    'payment_mode' => 'payment_mode',
                    'payment_amount' => 'payment_amount',
                    'or_date' => 'or_date',
                    'payment_or_number' => 'payment_or_number',
                    'additional_requests' => 'additional_requests',
                    'remarks' => 'remarks',
                ];

                foreach ($snapshotMapping as $formField => $dbField) {
                    if (array_key_exists($formField, $fields)) {
                        $snapshotUpdates[$dbField] = $fields[$formField];
                    }
                }

                if (! empty($snapshotUpdates)) {
                    $snapshot->update($snapshotUpdates);
                }
            }

            // Update reservation charges for add-ons if changed
            if (array_key_exists('additional_requests', $fields)) {
                $newItems = collect($fields['additional_requests'] ?? [])
                    ->filter(fn ($i) => ! empty($i['code'] ?? null))
                    ->values();

                // Clear existing addon charges
                $record->charges()->where('charge_type', 'addon')->delete();

                // Recreate addon charges with proper pricing and qty
                if ($newItems->isNotEmpty()) {
                    $addons = Service::query()
                        ->whereIn('code', $newItems->pluck('code')->unique())
                        ->get(['code', 'name', 'price'])
                        ->keyBy('code');

                    foreach ($newItems as $item) {
                        $code = $item['code'];
                        $qty = max(1, (int) ($item['qty'] ?? 1));
                        $addon = $addons->get($code);
                        if (! $addon) {
                            continue;
                        }
                        $price = (float) $addon->price;
                        ReservationCharge::create([
                            'reservation_id' => $record->id,
                            'charge_type' => 'addon',
                            'scope_type' => 'reservation',
                            'scope_id' => $record->id,
                            'description' => ($qty > 1 ? "{$qty}x " : '').$addon->name,
                            'qty' => $qty,
                            'unit_price' => $price,
                            'amount' => $price * $qty,
                            'currency' => 'PHP',
                            'meta' => [
                                'source' => 'edit_form',
                                'service_code' => $addon->code,
                                'qty' => $qty,
                            ],
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // Recalculate and update room charges if dates changed or if room charges need to be created
            $datesChanged = array_key_exists('detailed_checkin_datetime', $fields)
                || array_key_exists('detailed_checkout_datetime', $fields);

            if ($datesChanged || array_key_exists('additional_requests', $fields)) {
                // Clear existing room charges
                $record->charges()->where('charge_type', 'room_rate')->delete();

                // Compute room charges from current assignment data
                $assignments = $record->roomAssignments()->with('room.roomType')->get();

                // Use updated dates if provided, otherwise use reservation dates
                $checkInDate = null;
                $checkOutDate = null;

                if (array_key_exists('detailed_checkin_datetime', $fields) && $fields['detailed_checkin_datetime']) {
                    $checkInDate = \Carbon\Carbon::parse($fields['detailed_checkin_datetime']);
                }
                if (array_key_exists('detailed_checkout_datetime', $fields) && $fields['detailed_checkout_datetime']) {
                    $checkOutDate = \Carbon\Carbon::parse($fields['detailed_checkout_datetime']);
                }

                // Fallback to snapshot or assignment dates
                if (! $checkInDate || ! $checkOutDate) {
                    $snapshot = $record->checkInSnapshots()->latest('id')->first();
                    $latestAssignment = $assignments->first();

                    if (! $checkInDate) {
                        $checkInDate = $snapshot?->detailed_checkin_datetime
                            ?? $latestAssignment?->detailed_checkin_datetime
                            ?? $record->check_in_date;
                    }
                    if (! $checkOutDate) {
                        $checkOutDate = $snapshot?->detailed_checkout_datetime
                            ?? $latestAssignment?->detailed_checkout_datetime
                            ?? $record->check_out_date;
                    }
                }

                $checkInDate = \Carbon\Carbon::parse($checkInDate);
                $checkOutDate = \Carbon\Carbon::parse($checkOutDate);
                $nights = max(1, $checkInDate->diffInDays($checkOutDate));

                $totalRoomCharges = 0;
                foreach ($assignments->unique('room_id') as $assignment) {
                    if ($assignment->room && $assignment->room->roomType) {
                        $rate = (float) $assignment->room->roomType->base_rate;
                        $totalRoomCharges += $rate * $nights;
                    }
                }

                if ($totalRoomCharges > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $record->id,
                        'charge_type' => 'room_rate',
                        'scope_type' => 'reservation',
                        'scope_id' => $record->id,
                        'description' => "Room charges ({$nights} night".($nights > 1 ? 's' : '').')',
                        'qty' => 1,
                        'unit_price' => $totalRoomCharges,
                        'amount' => $totalRoomCharges,
                        'currency' => 'PHP',
                        'meta' => [
                            'source' => 'edit_form',
                            'nights' => $nights,
                            'check_in' => $checkInDate->toDateTimeString(),
                            'check_out' => $checkOutDate->toDateTimeString(),
                        ],
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // Recalculate discount charges when flags, dates, or add-ons change
            $discountFlagsChanged = array_key_exists('is_pwd', $fields)
                || array_key_exists('is_senior_citizen', $fields)
                || array_key_exists('is_student', $fields);

            if ($datesChanged || array_key_exists('additional_requests', $fields) || $discountFlagsChanged) {
                $latestAssignment = $record->roomAssignments()->latest('id')->first();
                $isPwd = (bool) ($fields['is_pwd'] ?? $latestAssignment?->is_pwd ?? false);
                $isSenior = (bool) ($fields['is_senior_citizen'] ?? $latestAssignment?->is_senior_citizen ?? false);
                $isStudent = (bool) ($fields['is_student'] ?? $latestAssignment?->is_student ?? false);

                $pwdPercent = (float) \App\Models\Setting::get('discount_pwd_percent', 0);
                $seniorPercent = (float) \App\Models\Setting::get('discount_senior_percent', 0);
                $studentPercent = (float) \App\Models\Setting::get('discount_student_percent', 0);

                $totalDiscountPercent = 0;
                $discountTypes = [];
                if ($isPwd && $pwdPercent > 0) {
                    $totalDiscountPercent += $pwdPercent;
                    $discountTypes[] = "PWD ({$pwdPercent}%)";
                }
                if ($isSenior && $seniorPercent > 0) {
                    $totalDiscountPercent += $seniorPercent;
                    $discountTypes[] = "Senior Citizen ({$seniorPercent}%)";
                }
                if ($isStudent && $studentPercent > 0) {
                    $totalDiscountPercent += $studentPercent;
                    $discountTypes[] = "Student ({$studentPercent}%)";
                }
                $totalDiscountPercent = min($totalDiscountPercent, 100);

                $roomChargeTotal = (float) $record->charges()->where('charge_type', 'room_rate')->sum('amount');
                $addonsChargeTotal = (float) $record->charges()->where('charge_type', 'addon')->sum('amount');
                $subtotal = $roomChargeTotal + $addonsChargeTotal;
                $discountAmount = ($subtotal * $totalDiscountPercent) / 100;

                // Replace existing discount charge
                $record->charges()->where('charge_type', 'discount')->delete();
                if ($discountAmount > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $record->id,
                        'charge_type' => 'discount',
                        'scope_type' => 'reservation',
                        'scope_id' => $record->id,
                        'description' => 'Discount: '.implode(' + ', $discountTypes),
                        'qty' => 1,
                        'unit_price' => -$discountAmount,
                        'amount' => -$discountAmount,
                        'currency' => 'PHP',
                        'meta' => [
                            'source' => 'edit_form',
                            'discount_types' => $discountTypes,
                            'discount_percent' => $totalDiscountPercent,
                            'subtotal_before_discount' => $subtotal,
                        ],
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            // Update payment record if payment details changed
            if (array_key_exists('payment_amount', $fields) || array_key_exists('payment_mode', $fields) || array_key_exists('payment_or_number', $fields) || array_key_exists('or_date', $fields)) {
                $latestPayment = $record->payments()->where('status', 'posted')->latest('id')->first();

                if ($latestPayment) {
                    $paymentUpdates = [];
                    if (array_key_exists('payment_amount', $fields)) {
                        $paymentUpdates['amount'] = (float) $fields['payment_amount'];
                    }
                    if (array_key_exists('payment_mode', $fields)) {
                        $paymentUpdates['payment_mode'] = $fields['payment_mode'];
                    }
                    if (array_key_exists('payment_or_number', $fields)) {
                        $paymentUpdates['reference_no'] = $fields['payment_or_number'];
                    }
                    if (array_key_exists('or_date', $fields)) {
                        $paymentUpdates['or_date'] = $fields['or_date'];
                    }

                    if (! empty($paymentUpdates)) {
                        $latestPayment->update($paymentUpdates);
                    }
                } elseif (array_key_exists('payment_amount', $fields) && (float) $fields['payment_amount'] > 0) {
                    // Create new payment record if none exists but payment amount is provided
                    ReservationPayment::create([
                        'reservation_id' => $record->id,
                        'amount' => (float) $fields['payment_amount'],
                        'payment_mode' => $fields['payment_mode'] ?? 'cash',
                        'reference_no' => $fields['payment_or_number'] ?? null,
                        'or_date' => $fields['or_date'] ?? null,
                        'status' => 'posted',
                        'received_by' => auth()->id(),
                        'received_at' => now(),
                        'remarks' => 'Payment record created from edit form',
                        'meta' => ['source' => 'edit_form'],
                    ]);
                }
            }

            // Refresh financial summary to update totals
            $record->refreshFinancialSummary();
        });

        Notification::make()
            ->success()
            ->title('Reservation updated')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->successNotificationTitle('Reservation deleted'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
