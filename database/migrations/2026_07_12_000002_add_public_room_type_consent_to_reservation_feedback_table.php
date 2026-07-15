<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_feedback', function (Blueprint $table) {
            $table->boolean('public_display_room_type')->default(false)->after('public_display_consent');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_feedback', function (Blueprint $table) {
            $table->dropColumn('public_display_room_type');
        });
    }
};
