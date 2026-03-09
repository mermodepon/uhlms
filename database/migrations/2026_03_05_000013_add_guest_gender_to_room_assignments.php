<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->string('guest_gender')->nullable()->after('guest_middle_initial');
        });
    }

    public function down(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropColumn('guest_gender');
        });
    }
};
