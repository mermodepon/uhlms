<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info'); // info, success, warning, danger
            $table->string('category')->nullable(); // reservation, room, user, system etc
            $table->morphs('notifiable');
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('notifiable_type');
            $table->index('notifiable_id');
            $table->index('is_read');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
