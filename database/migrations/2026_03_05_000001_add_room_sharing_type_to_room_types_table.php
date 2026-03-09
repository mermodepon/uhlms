<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            // 'public'  → shared/dormitory-style: multiple guests can share the room up to capacity
            // 'private' → exclusive: once any guest is assigned, the entire room is locked for that reservation
            $table->enum('room_sharing_type', ['public', 'private'])
                ->default('public')
                ->after('pricing_type')
                ->comment('public = dormitory-style (shared); private = exclusive to one reservation');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('room_sharing_type');
        });
    }
};
