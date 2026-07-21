<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppendReservationDemoSeeder extends ReservationDemoSeeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $year = Carbon::today()->year;
            $prefix = $year.'-';
            $lastReference = DB::table('reservations')
                ->where('reference_number', 'like', $prefix.'%')
                ->orderByDesc('reference_number')
                ->value('reference_number');
            $startSequence = $lastReference
                ? ((int) substr((string) $lastReference, strlen($prefix))) + 1
                : 1;

            if ($startSequence !== 9) {
                throw new RuntimeException("Expected the next reservation reference to be {$year}-0009; found sequence {$startSequence}.");
            }

            $statusPlan = array_merge(
                array_fill(0, 10, 'pending'),
                array_fill(0, 8, 'approved'),
                array_fill(0, 12, 'confirmed'),
                array_fill(0, 8, 'checked_in'),
                array_fill(0, 8, 'checked_out'),
                array_fill(0, 2, 'declined'),
                array_fill(0, 2, 'cancelled'),
            );

            $this->seedReservations($startSequence, $statusPlan);

            DB::table('reservation_sequences')->updateOrInsert(
                ['year' => $year],
                ['last_sequence' => $startSequence + count($statusPlan) - 1],
            );
        });
    }
}
