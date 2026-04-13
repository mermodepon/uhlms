<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_waypoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['entrance', 'lobby', 'hallway', 'room-door', 'room-interior', 'amenity', 'common-area']);
            $table->string('panorama_image')->comment('Path to 360° panorama image');
            $table->string('thumbnail_image')->nullable()->comment('Thumbnail for mini-map');
            $table->integer('position_order')->default(0)->comment('Sequence in tour');
            $table->foreignId('linked_room_type_id')->nullable()->constrained('room_types')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('narration')->nullable()->comment('Auto-displayed tooltip at this waypoint');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index('position_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_waypoints');
    }
};
