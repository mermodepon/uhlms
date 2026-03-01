<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Composite index for the most common queries
            $table->index(['notifiable_type', 'notifiable_id', 'created_at']);
            $table->index(['notifiable_type', 'notifiable_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'created_at']);
            $table->dropIndex(['notifiable_type', 'notifiable_id', 'is_read']);
        });
    }
};
