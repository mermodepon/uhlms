<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();

            // Guest information (submitted by guest via public form)
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->text('guest_address')->nullable();

            // Reservation details
            $table->foreignId('preferred_room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('number_of_occupants')->default(1);
            $table->string('purpose')->nullable(); // academic, official, personal, event, other
            $table->text('special_requests')->nullable();

            // Status workflow: pending → approved/declined → checked_in → checked_out
            $table->enum('status', [
                'pending',
                'approved',
                'declined',
                'cancelled',
                'checked_in',
                'checked_out',
            ])->default('pending');

            // Staff review
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
