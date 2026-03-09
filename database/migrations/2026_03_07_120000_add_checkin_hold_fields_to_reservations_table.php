<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('checkin_hold_payload')->nullable()->after('admin_notes');
            $table->timestamp('checkin_hold_started_at')->nullable()->after('checkin_hold_payload');
            $table->timestamp('checkin_hold_expires_at')->nullable()->after('checkin_hold_started_at');
            $table->foreignId('checkin_hold_by')->nullable()->after('checkin_hold_expires_at')->constrained('users')->nullOnDelete();
            $table->index(['status', 'checkin_hold_expires_at'], 'reservations_status_hold_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['checkin_hold_by']);
            $table->dropIndex('reservations_status_hold_expires_idx');
            $table->dropColumn([
                'checkin_hold_payload',
                'checkin_hold_started_at',
                'checkin_hold_expires_at',
                'checkin_hold_by',
            ]);
        });
    }
};
