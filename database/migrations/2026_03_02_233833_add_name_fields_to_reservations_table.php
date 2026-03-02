<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('guest_last_name')->nullable()->after('guest_name');
            $table->string('guest_first_name')->nullable()->after('guest_last_name');
            $table->string('guest_middle_initial', 10)->nullable()->after('guest_first_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['guest_last_name', 'guest_first_name', 'guest_middle_initial']);
        });
    }
};
