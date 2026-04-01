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
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('base_rate', 10, 2);
            $table->enum('pricing_type', ['flat_rate', 'per_person'])->default('flat_rate');
            $table->enum('room_sharing_type', ['public', 'private'])->default('public');
            $table->json('images')->nullable();
            $table->string('virtual_tour_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
