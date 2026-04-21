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
        Schema::table('reservations', function (Blueprint $table) {
            // Discount declaration fields
            $table->boolean('discount_declared')->default(false);
            $table->enum('discount_declared_type', ['senior_citizen', 'pwd', 'student'])->nullable();
            $table->boolean('discount_verified')->default(false);
            $table->text('discount_verification_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'discount_declared',
                'discount_declared_type',
                'discount_verified',
                'discount_verification_notes',
            ]);
        });
    }
};
