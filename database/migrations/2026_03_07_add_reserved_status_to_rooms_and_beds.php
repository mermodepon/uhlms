<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify rooms table to add 'reserved' status
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance', 'inactive', 'reserved'])
                ->change()
                ->default('available');
        });

        // Modify beds table to add 'reserved' status
        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'reserved'])
                ->change()
                ->default('available');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied', 'maintenance', 'inactive'])
                ->change()
                ->default('available');
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->enum('status', ['available', 'occupied'])
                ->change()
                ->default('available');
        });
    }
};
