<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_hotspots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waypoint_id')->constrained('tour_waypoints')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->default('info-circle')->comment('Heroicon name');
            $table->decimal('pitch', 8, 4)->comment('Vertical position in panorama');
            $table->decimal('yaw', 8, 4)->comment('Horizontal position in panorama');
            $table->enum('action_type', ['info', 'navigate', 'bookmark', 'external-link'])->default('info');
            $table->string('action_target')->nullable()->comment('Target waypoint slug, URL, or action identifier');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['waypoint_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_hotspots');
    }
};
