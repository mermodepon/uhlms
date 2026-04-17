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
            $table->foreignId('linked_room_id')
                ->nullable()
                ->after('linked_room_type_id')
                ->constrained('rooms')
                ->nullOnDelete()
                ->comment('Specific room preference (takes priority over room type)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_waypoints', function (Blueprint $table) {
            $table->dropForeign(['linked_room_id']);
            $table->dropColumn('linked_room_id');
        });
    }
};
