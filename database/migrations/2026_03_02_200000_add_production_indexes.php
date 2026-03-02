<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production optimization: Add indexes for frequently queried columns.
 *
 * These indexes are based on actual query patterns in Filament resources,
 * guest controllers, widgets, and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        // rooms: status + is_active — used by available room queries in GuestController,
        // ReservationResource room assignment, RoomStatusChart widget
        Schema::table('rooms', function (Blueprint $table) {
            $table->index(['status', 'is_active'], 'rooms_status_is_active_index');
            $table->index(['room_type_id', 'status', 'is_active'], 'rooms_type_status_active_index');
        });

        // reservations: status — used by navigation badges, filters, StatsOverview widget
        Schema::table('reservations', function (Blueprint $table) {
            $table->index('status', 'reservations_status_index');
            $table->index(['status', 'check_in_date'], 'reservations_status_checkin_index');
            $table->index(['status', 'check_out_date'], 'reservations_status_checkout_index');
            $table->index(['check_in_date', 'check_out_date'], 'reservations_date_range_index');
            $table->index('guest_email', 'reservations_guest_email_index');
        });

        // stay_logs: checked_in/out timestamps — used by occupancy reports, StatsOverview
        Schema::table('stay_logs', function (Blueprint $table) {
            $table->index(['checked_in_at', 'checked_out_at'], 'stay_logs_checkin_checkout_index');
            $table->index('room_id', 'stay_logs_room_id_index');
        });

        // messages: is_read + sender_type — used by navigation badge count
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['is_read', 'sender_type'], 'messages_read_sender_type_index');
            $table->index('reservation_id', 'messages_reservation_id_index');
        });

        // settings: key lookup (already unique, but add explicit index for cache queries)
        Schema::table('settings', function (Blueprint $table) {
            $table->index('key', 'settings_key_index');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_status_is_active_index');
            $table->dropIndex('rooms_type_status_active_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_status_index');
            $table->dropIndex('reservations_status_checkin_index');
            $table->dropIndex('reservations_status_checkout_index');
            $table->dropIndex('reservations_date_range_index');
            $table->dropIndex('reservations_guest_email_index');
        });

        Schema::table('stay_logs', function (Blueprint $table) {
            $table->dropIndex('stay_logs_checkin_checkout_index');
            $table->dropIndex('stay_logs_room_id_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_read_sender_type_index');
            $table->dropIndex('messages_reservation_id_index');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex('settings_key_index');
        });
    }
};
