<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')->where('status', 'pending_payment')->orderBy('id')->eachById(function (object $reservation): void {
            $hasAdvanceHold = DB::table('room_holds')
                ->where('reservation_id', $reservation->id)
                ->where('hold_type', 'advance')
                ->exists();

            DB::table('reservations')->where('id', $reservation->id)->update([
                'status' => $hasAdvanceHold ? 'confirmed' : 'approved',
                'updated_at' => now(),
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `reservations` MODIFY COLUMN `status` ENUM('pending','awaiting_alternative_confirmation','approved','confirmed','declined','cancelled','checked_in','checked_out') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the previous state cannot be inferred safely.
    }
};
