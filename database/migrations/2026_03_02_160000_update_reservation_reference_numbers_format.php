<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing reservations that have old format (RES-XXXXXXXX)
        $reservations = DB::table('reservations')
            ->where('reference_number', 'like', 'RES-%')
            ->orderBy('created_at')
            ->get();

        foreach ($reservations as $index => $reservation) {
            $year = date('Y', strtotime($reservation->created_at));
            
            // Get count of reservations in that year before this one
            $yearlyCount = DB::table('reservations')
                ->whereYear('created_at', $year)
                ->where('id', '<=', $reservation->id)
                ->count();
            
            $sequenceNumber = str_pad($yearlyCount, 4, '0', STR_PAD_LEFT);
            $newReference = $year . '-' . $sequenceNumber;
            
            // Update the reference number
            DB::table('reservations')
                ->where('id', $reservation->id)
                ->update(['reference_number' => $newReference]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this migration as it's a data update
        // The old format can't be accurately restored
    }
};
