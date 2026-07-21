<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AppendFirstHalfJulyReservationSeeder extends ReservationDemoSeeder
{
    /** @var array<int, Carbon> */
    private array $checkInDates = [];

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

            if ($startSequence !== 59) {
                throw new RuntimeException("Expected the next reservation reference to be {$year}-0059; found sequence {$startSequence}.");
            }

            foreach (range(1, 15) as $day) {
                $this->checkInDates[$startSequence + $day - 1] = Carbon::create($year, 7, $day)->startOfDay();
            }

            $this->seedReservations($startSequence, array_fill(0, 15, 'checked_out'));

            DB::table('reservation_sequences')->updateOrInsert(
                ['year' => $year],
                ['last_sequence' => $startSequence + 14],
            );
        });
    }

    protected function buildDateWindow(string $status, Carbon $today, int $sequence, int $attempt): array
    {
        if ($status === 'checked_out' && isset($this->checkInDates[$sequence])) {
            return $this->window($this->checkInDates[$sequence], 1 + (($sequence + $attempt) % 3));
        }

        return parent::buildDateWindow($status, $today, $sequence, $attempt);
    }
}
