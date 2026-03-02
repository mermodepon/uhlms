<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add capacity column to rooms table
        Schema::table('rooms', function (Blueprint $table) {
            $table->integer('capacity')->default(1)->after('floor_id');
        });

        // Migrate existing data: copy capacity from room_types to rooms
        DB::statement('
            UPDATE rooms 
            INNER JOIN room_types ON rooms.room_type_id = room_types.id 
            SET rooms.capacity = room_types.capacity
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('capacity');
        });
    }
};
