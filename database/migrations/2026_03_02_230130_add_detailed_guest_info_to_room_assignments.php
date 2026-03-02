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
        Schema::table('room_assignments', function (Blueprint $table) {
            // Detailed guest information
            $table->string('guest_last_name')->nullable()->after('notes');
            $table->string('guest_first_name')->nullable()->after('guest_last_name');
            $table->string('guest_middle_initial', 10)->nullable()->after('guest_first_name');
            $table->text('guest_full_address')->nullable()->after('guest_middle_initial');
            $table->string('guest_contact_number', 20)->nullable()->after('guest_full_address');
            
            // ID Information
            $table->string('id_type')->nullable()->after('guest_contact_number'); // e.g., "Driver's License", "Passport", "Student ID"
            $table->string('id_number')->nullable()->after('id_type');
            
            // Guest Status
            $table->boolean('is_student')->default(false)->after('id_number');
            $table->boolean('is_senior_citizen')->default(false)->after('is_student');
            $table->boolean('is_pwd')->default(false)->after('is_senior_citizen');
            
            // Stay Details
            $table->string('purpose_of_stay')->nullable()->after('is_pwd');
            $table->string('nationality')->default('Filipino')->after('purpose_of_stay');
            $table->integer('num_male_guests')->default(0)->after('nationality');
            $table->integer('num_female_guests')->default(0)->after('num_male_guests');
            
            // Check-in/out with time
            $table->dateTime('detailed_checkin_datetime')->nullable()->after('num_female_guests');
            $table->dateTime('detailed_checkout_datetime')->nullable()->after('detailed_checkin_datetime');
            
            // Additional Services
            $table->json('additional_requests')->nullable()->after('detailed_checkout_datetime'); // ["towels", "extra_bed", "iron_rental"]
            
            // Payment Information
            $table->string('payment_mode')->nullable()->after('additional_requests'); // "cash", "bank_transfer", etc.
            $table->string('payment_mode_other')->nullable()->after('payment_mode'); // If "others" is selected
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_mode_other');
            $table->string('payment_or_number')->nullable()->after('payment_amount'); // Official Receipt Number
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'guest_last_name',
                'guest_first_name',
                'guest_middle_initial',
                'guest_full_address',
                'guest_contact_number',
                'id_type',
                'id_number',
                'is_student',
                'is_senior_citizen',
                'is_pwd',
                'purpose_of_stay',
                'nationality',
                'num_male_guests',
                'num_female_guests',
                'detailed_checkin_datetime',
                'detailed_checkout_datetime',
                'additional_requests',
                'payment_mode',
                'payment_mode_other',
                'payment_amount',
                'payment_or_number',
            ]);
        });
    }
};
