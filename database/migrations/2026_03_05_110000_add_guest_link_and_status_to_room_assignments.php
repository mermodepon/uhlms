<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->foreignId('guest_id')
                ->nullable()
                ->after('reservation_id')
                ->constrained('guests')
                ->nullOnDelete();

            $table->enum('status', ['checked_in', 'checked_out'])
                ->default('checked_in')
                ->after('checked_out_by');
        });

        DB::table('room_assignments')
            ->whereNotNull('checked_out_at')
            ->update(['status' => 'checked_out']);
    }

    public function down(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropForeign(['guest_id']);
            $table->dropColumn('guest_id');
        });
    }
};
