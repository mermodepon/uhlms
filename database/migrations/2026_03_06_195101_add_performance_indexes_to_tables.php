<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to reservations table for frequently queried columns
        Schema::table('reservations', function (Blueprint $table) {
            $table->index('status', 'idx_reservations_status');
            $table->index(['check_in_date', 'check_out_date'], 'idx_reservations_dates');
        });

        // Add indexes to room_assignments table
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->index('reservation_id', 'idx_room_assignments_reservation');
            $table->index('room_id', 'idx_room_assignments_room');
            $table->index('status', 'idx_room_assignments_status');
            $table->index(['checked_in_at', 'checked_out_at'], 'idx_room_assignments_checkin_checkout');
        });

        // Add indexes to notifications table for performance
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'is_read'], 'idx_notifications_notifiable_read');
            $table->index('created_at', 'idx_notifications_created_at');
        });

        // Add indexes to rooms table
        Schema::table('rooms', function (Blueprint $table) {
            $table->index('status', 'idx_rooms_status');
            $table->index('room_type_id', 'idx_rooms_room_type');
            $table->index('is_active', 'idx_rooms_is_active');
        });

        // Add indexes to beds table
        Schema::table('beds', function (Blueprint $table) {
            $table->index('room_id', 'idx_beds_room');
            $table->index('status', 'idx_beds_status');
        });

        // Add indexes to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->index('reservation_id', 'idx_messages_reservation');
            $table->index(['sender_type', 'sender_id'], 'idx_messages_sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('idx_reservations_status');
            $table->dropIndex('idx_reservations_dates');
        });

        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_room_assignments_reservation');
            $table->dropIndex('idx_room_assignments_room');
            $table->dropIndex('idx_room_assignments_status');
            $table->dropIndex('idx_room_assignments_checkin_checkout');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_notifiable_read');
            $table->dropIndex('idx_notifications_created_at');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_status');
            $table->dropIndex('idx_rooms_room_type');
            $table->dropIndex('idx_rooms_is_active');
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->dropIndex('idx_beds_room');
            $table->dropIndex('idx_beds_status');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_reservation');
            $table->dropIndex('idx_messages_sender');
        });
    }
};
