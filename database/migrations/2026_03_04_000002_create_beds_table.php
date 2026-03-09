<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each physical bunk/bed slot inside a dorm room.
     * Beds are created when a room is configured by staff.
     * A RoomAssignment links exactly one guest to one bed.
     */
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();

            // Human-readable identifier, e.g. "Bed 1", "Lower A1", "Upper B2"
            $table->string('bed_number', 50);

            // available = free to assign | occupied = guest currently using it
            $table->enum('status', ['available', 'occupied'])->default('available');

            $table->timestamps();

            $table->unique(['room_id', 'bed_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
