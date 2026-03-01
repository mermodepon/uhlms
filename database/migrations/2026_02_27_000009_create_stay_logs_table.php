<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_logs');
    }
};
