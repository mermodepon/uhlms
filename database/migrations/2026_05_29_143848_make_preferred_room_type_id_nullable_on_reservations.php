<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['preferred_room_type_id']);
            $table->unsignedBigInteger('preferred_room_type_id')->nullable()->change();
            $table->foreign('preferred_room_type_id')
                ->references('id')->on('room_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['preferred_room_type_id']);
            $table->unsignedBigInteger('preferred_room_type_id')->nullable(false)->change();
            $table->foreign('preferred_room_type_id')
                ->references('id')->on('room_types')
                ->restrictOnDelete();
        });
    }
};
