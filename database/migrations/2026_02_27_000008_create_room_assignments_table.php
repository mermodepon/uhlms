<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
    }
};
