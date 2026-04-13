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
        Schema::table('tour_waypoints', function (Blueprint $table) {
            $table->decimal('room_info_yaw', 8, 4)->nullable()->after('linked_room_type_id')
                  ->comment('Override yaw for the Room Info system marker (null = uses default_yaw)');
            $table->decimal('room_info_pitch', 8, 4)->nullable()->after('room_info_yaw')
                  ->comment('Override pitch for the Room Info system marker (null = default_pitch + 15)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_waypoints', function (Blueprint $table) {
            $table->dropColumn(['room_info_yaw', 'room_info_pitch']);
        });
    }
};
