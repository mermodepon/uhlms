<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add bed_id to room_assignments so each assignment can target a specific bed.
     * Also removes the implicit "one assignment per reservation" model in favor of
     * one assignment per person (multiple guests → multiple assignments, one bed each).
     */
    public function up(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            // Nullable so existing rows without bed data are still valid
            $table->foreignId('bed_id')
                ->nullable()
                ->after('room_id')
                ->constrained('beds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropForeign(['bed_id']);
            $table->dropColumn('bed_id');
        });
    }
};
