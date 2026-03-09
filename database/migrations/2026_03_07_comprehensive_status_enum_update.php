<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update reservations table to add 'pending_payment' status
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'pending_payment',
                'declined',
                'cancelled',
                'checked_in',
                'checked_out',
            ])->change();
        });

        // Verify rooms table has all needed statuses (including 'reserved')
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance', 'inactive', 'reserved'])
                ->change();
        });

        // Verify beds table has all needed statuses (including 'reserved')
        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'reserved'])
                ->change();
        });

        // Verify room_assignments has all needed statuses
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->enum('status', ['checked_in', 'checked_out'])
                ->change();
        });
    }

    public function down(): void
    {
        // Revert reservations status enum
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'declined',
                'cancelled',
                'checked_in',
                'checked_out',
            ])->change();
        });

        // Revert rooms status enum
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance', 'inactive'])
                ->change();
        });

        // Revert beds status enum
        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied'])
                ->change();
        });
    }
};
