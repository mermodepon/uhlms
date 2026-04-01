<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Handles the circular FK: guests.reservation_id → reservations,
// and reservations.billing_guest_id → guests.
// Solution: create guests first, then add billing_guest_id FK to reservations.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('middle_initial')->nullable();
            $table->string('relationship_to_primary')->nullable();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Now that guests exists, add the billing_guest_id FK back to reservations
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('billing_guest_id')
                ->nullable()
                ->after('preferred_room_type_id')
                ->constrained('guests')
                ->nullOnDelete();
            $table->index('billing_guest_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['billing_guest_id']);
            $table->dropIndex(['billing_guest_id']);
            $table->dropColumn('billing_guest_id');
        });

        Schema::dropIfExists('guests');
    }
};
