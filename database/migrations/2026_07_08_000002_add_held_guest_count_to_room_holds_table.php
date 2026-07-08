<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_holds', function (Blueprint $table) {
            $table->unsignedSmallInteger('held_guest_count')->nullable()->after('hold_type');
        });
    }

    public function down(): void
    {
        Schema::table('room_holds', function (Blueprint $table) {
            $table->dropColumn('held_guest_count');
        });
    }
};
