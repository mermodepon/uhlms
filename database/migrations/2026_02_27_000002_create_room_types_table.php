<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., Single, Double, Suite, Dormitory
            $table->text('description')->nullable();
            $table->integer('capacity')->default(1);
            $table->decimal('base_rate', 10, 2);
            $table->json('images')->nullable(); // Up to 5 images
            $table->string('virtual_tour_url')->nullable(); // Panoee embed URL
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
