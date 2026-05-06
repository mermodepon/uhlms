<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove 'pending_payment' status from reservations table enum
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER TABLE MODIFY, but it doesn't enforce enum types
            // so we can skip this migration in test environments
            return;
        }

        DB::statement("
            ALTER TABLE reservations 
            MODIFY COLUMN status ENUM(
                'pending', 
                'approved', 
                'confirmed',
                'declined', 
                'cancelled', 
                'checked_in', 
                'checked_out'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add 'pending_payment' status
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER TABLE MODIFY, but it doesn't enforce enum types
            return;
        }

        DB::statement("
            ALTER TABLE reservations 
            MODIFY COLUMN status ENUM(
                'pending', 
                'approved', 
                'confirmed',
                'pending_payment',
                'declined', 
                'cancelled', 
                'checked_in', 
                'checked_out'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};
