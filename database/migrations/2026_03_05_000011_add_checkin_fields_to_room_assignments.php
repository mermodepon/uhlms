<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add check-in/out timestamps and actor columns to room_assignments.
     * Also provide a small backfill from existing stay_logs so data remains
     * consistent.  This migration does **not** drop the stay_logs table – keep
     * it around for auditing until the application has been fully converted.
     */
    public function up(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('assigned_at');
            $table->foreignId('checked_in_by')
                ->nullable()
                ->after('checked_in_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('checked_out_at')->nullable()->after('checked_in_by');
            $table->foreignId('checked_out_by')
                ->nullable()
                ->after('checked_out_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('remarks')->nullable()->after('notes');
        });

        // backfill from stay_logs for existing assignments
        // - use the earliest checked_in_at and the latest checked_out_at per assignment
        $assignments = DB::table('room_assignments')->get();

        foreach ($assignments as $a) {
            $log = DB::table('stay_logs')
                ->where('reservation_id', $a->reservation_id)
                ->where('room_id', $a->room_id)
                ->orderBy('checked_in_at', 'asc')
                ->first();

            if ($log) {
                DB::table('room_assignments')
                    ->where('id', $a->id)
                    ->update([
                        'checked_in_at' => $log->checked_in_at,
                        'checked_in_by' => $log->checked_in_by,
                        'checked_out_at' => $log->checked_out_at,
                        'checked_out_by' => $log->checked_out_by,
                        'remarks' => $log->remarks,
                    ]);
            }
        }

        // report orphaned logs (those not matching any assignment)
        $orphans = DB::table('stay_logs')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('room_assignments')
                    ->whereColumn('room_assignments.reservation_id', 'stay_logs.reservation_id')
                    ->whereColumn('room_assignments.room_id', 'stay_logs.room_id');
            })
            ->count();

        if ($orphans) {
            echo "WARNING: $orphans stay_logs entries lack a corresponding room_assignments record.\n";
        }
    }

    public function down(): void
    {
        Schema::table('room_assignments', function (Blueprint $table) {
            $table->dropForeign(['checked_out_by']);
            $table->dropColumn('checked_out_by');
            $table->dropColumn('checked_out_at');

            $table->dropForeign(['checked_in_by']);
            $table->dropColumn('checked_in_by');
            $table->dropColumn('checked_in_at');

            $table->dropColumn('remarks');
        });
    }
};
