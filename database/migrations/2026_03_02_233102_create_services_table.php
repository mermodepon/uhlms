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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Extra Towels", "Iron Rental"
            $table->string('code')->unique(); // e.g., "extra_towels", "iron_rental"
            $table->string('category')->default('amenity'); // amenity, equipment, special_service
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0.00); // Price for the service (0.00 if free)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // For custom ordering in forms
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
