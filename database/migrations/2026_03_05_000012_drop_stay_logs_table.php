<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the legacy stay_logs table once data has been migrated into
     * room_assignments.  This migration is meant to be applied **after** you
     * have verified the consistency-check migration run earlier and have
     * confirmed that no business logic still depends on stay_logs.
     *
     * WARNING: this is destructive.  A backup of the table is recommended.
     */
    public function up(): void
    {
        if (Schema::hasTable('stay_logs')) {
            $count = DB::table('stay_logs')->count();
            echo "Dropping stay_logs table (currently {$count} rows).\n";
            Schema::dropIfExists('stay_logs');
        }
    }

    public function down(): void
    {
        // In case you need to roll back after dropping, recreate the schema.
        Schema::create('stay_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();

            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }
};
