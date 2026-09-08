<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_room_requests', function (Blueprint $table): void {
            $table->json('requested_room_ids')->nullable()->after('requested_room_count');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_room_requests', function (Blueprint $table): void {
            $table->dropColumn('requested_room_ids');
        });
    }
};
