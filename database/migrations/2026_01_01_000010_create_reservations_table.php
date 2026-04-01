<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: billing_guest_id FK is added in create_guests_table migration to resolve
// the circular dependency between reservations and guests.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();

            // Guest identity
            $table->string('guest_name');
            $table->string('guest_last_name')->nullable();
            $table->string('guest_first_name')->nullable();
            $table->string('guest_middle_initial', 10)->nullable();
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->text('guest_address')->nullable();
            $table->string('guest_gender', 20)->nullable();
            $table->unsignedSmallInteger('guest_age')->nullable();
            $table->integer('num_male_guests')->default(0);
            $table->integer('num_female_guests')->default(0);

            // Booking details
            $table->foreignId('preferred_room_type_id')->constrained('room_types')->restrictOnDelete();
            // billing_guest_id added after guests table is created (see create_guests_table migration)
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->integer('number_of_occupants')->default(1);
            $table->string('purpose')->nullable();
            $table->text('special_requests')->nullable();

            // Status
            $table->enum('status', [
                'pending',
                'approved',
                'pending_payment',
                'declined',
                'cancelled',
                'checked_in',
                'checked_out',
            ])->default('pending');

            // Staff review
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Check-in hold
            $table->json('checkin_hold_payload')->nullable();
            $table->timestamp('checkin_hold_started_at')->nullable();
            $table->timestamp('checkin_hold_expires_at')->nullable();
            $table->foreignId('checkin_hold_by')->nullable()->constrained('users')->nullOnDelete();

            // Financial summary
            $table->decimal('addons_total', 10, 2)->default(0);
            $table->decimal('payments_total', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->string('payment_status')->default('pending');

            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'check_in_date']);
            $table->index(['status', 'check_out_date']);
            $table->index(['check_in_date', 'check_out_date']);
            $table->index('guest_email');
            $table->index(['status', 'checkin_hold_expires_at']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
