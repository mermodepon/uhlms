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
            $table->decimal('default_yaw', 8, 4)->default(0)->after('panorama_image');
            $table->decimal('default_pitch', 8, 4)->default(0)->after('default_yaw');
            $table->unsignedTinyInteger('default_zoom')->default(50)->after('default_pitch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_waypoints', function (Blueprint $table) {
            $table->dropColumn(['default_yaw', 'default_pitch', 'default_zoom']);
        });
    }
};
