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
        Schema::create('room_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->date('hold_from');       // check-in date range start
            $table->date('hold_to');         // check-out date range end
            $table->string('hold_type')->default('advance'); // advance | short_term
            $table->timestamp('expires_at')->nullable();     // null = no expiry (advance hold); set for short-term
            $table->timestamps();

            $table->index(['room_id', 'hold_from', 'hold_to']);
            $table->index(['reservation_id']);
            $table->index(['hold_type', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_holds');
    }
};
