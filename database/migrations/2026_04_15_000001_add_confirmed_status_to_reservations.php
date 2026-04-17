<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'confirmed' status to the enum
        DB::statement("ALTER TABLE `reservations` MODIFY COLUMN `status` ENUM(
            'pending',
            'approved',
            'confirmed',
            'pending_payment',
            'declined',
            'cancelled',
            'checked_in',
            'checked_out'
        ) NOT NULL DEFAULT 'pending'");

        // Automatically transition existing 'approved' reservations with advance holds to 'confirmed'
        DB::statement("
            UPDATE reservations r
            INNER JOIN (
                SELECT reservation_id
                FROM room_holds
                WHERE hold_type = 'advance'
                GROUP BY reservation_id
            ) h ON r.id = h.reservation_id
            SET r.status = 'confirmed'
            WHERE r.status = 'approved'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'confirmed' reservations back to 'approved'
        DB::statement("UPDATE reservations SET status = 'approved' WHERE status = 'confirmed'");

        // Remove 'confirmed' from enum
        DB::statement("ALTER TABLE `reservations` MODIFY COLUMN `status` ENUM(
            'pending',
            'approved',
            'pending_payment',
            'declined',
            'cancelled',
            'checked_in',
            'checked_out'
        ) NOT NULL DEFAULT 'pending'");
    }
};
