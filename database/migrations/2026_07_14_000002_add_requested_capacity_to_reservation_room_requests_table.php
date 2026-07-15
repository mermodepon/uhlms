<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_room_requests', function (Blueprint $table): void {
            $table->unsignedSmallInteger('requested_capacity')
                ->nullable()
                ->after('room_type_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_room_requests', function (Blueprint $table): void {
            $table->dropIndex(['requested_capacity']);
            $table->dropColumn('requested_capacity');
        });
    }
};
