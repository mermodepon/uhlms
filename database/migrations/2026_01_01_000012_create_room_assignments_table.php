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

            // Relations
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            // Check-in / check-out tracking
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['checked_in', 'checked_out'])->default('checked_in');

            // Notes
            $table->text('notes')->nullable();
            $table->text('remarks')->nullable();

            // Guest identity
            $table->string('guest_last_name')->nullable();
            $table->string('guest_first_name')->nullable();
            $table->string('guest_middle_initial', 10)->nullable();
            $table->string('guest_gender')->nullable();
            $table->unsignedSmallInteger('guest_age')->nullable();
            $table->text('guest_full_address')->nullable();
            $table->string('guest_contact_number', 20)->nullable();

            // ID information
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();

            // Guest status flags
            $table->boolean('is_student')->default(false);
            $table->boolean('is_senior_citizen')->default(false);
            $table->boolean('is_pwd')->default(false);

            // Stay details
            $table->string('purpose_of_stay')->nullable();
            $table->string('nationality')->default('Filipino');
            $table->integer('num_male_guests')->default(0);
            $table->integer('num_female_guests')->default(0);
            $table->dateTime('detailed_checkin_datetime')->nullable();
            $table->dateTime('detailed_checkout_datetime')->nullable();
            $table->json('additional_requests')->nullable();

            // Payment
            $table->string('payment_mode')->nullable();
            $table->string('payment_mode_other')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->string('payment_or_number')->nullable();
            $table->date('or_date')->nullable();

            $table->timestamps();

            $table->index('reservation_id');
            $table->index('room_id');
            $table->index('status');
            $table->index(['checked_in_at', 'checked_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
    }
};
