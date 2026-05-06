<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasColumn('reservations', 'checkin_hold_expires_at')) {
            try {
                Schema::table('reservations', function (Blueprint $table) {
                    $table->dropIndex(['status', 'checkin_hold_expires_at']);
                });
            } catch (Throwable) {
                // The index may not exist in older or test databases.
            }
        }

        if (Schema::hasColumn('reservations', 'checkin_hold_by')) {
            try {
                Schema::table('reservations', function (Blueprint $table) {
                    $table->dropForeign(['checkin_hold_by']);
                });
            } catch (Throwable) {
                // The foreign key may already be absent in some environments.
            }

            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('checkin_hold_by');
            });
        }

        foreach (['checkin_hold_payload', 'checkin_hold_started_at', 'checkin_hold_expires_at'] as $column) {
            if (Schema::hasColumn('reservations', $column)) {
                Schema::table('reservations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('reservations', 'checkin_hold_payload')) {
                $table->json('checkin_hold_payload')->nullable()->after('admin_notes');
            }

            if (! Schema::hasColumn('reservations', 'checkin_hold_started_at')) {
                $table->timestamp('checkin_hold_started_at')->nullable()->after('checkin_hold_payload');
            }

            if (! Schema::hasColumn('reservations', 'checkin_hold_expires_at')) {
                $table->timestamp('checkin_hold_expires_at')->nullable()->after('checkin_hold_started_at');
            }

            if (! Schema::hasColumn('reservations', 'checkin_hold_by')) {
                $table->foreignId('checkin_hold_by')->nullable()->after('checkin_hold_expires_at');
            }
        });

        if (Schema::hasColumn('reservations', 'checkin_hold_by') && DB::getDriverName() !== 'sqlite') {
            try {
                Schema::table('reservations', function (Blueprint $table) {
                    $table->foreign('checkin_hold_by')->references('id')->on('users')->nullOnDelete();
                });
            } catch (Throwable) {
                // The foreign key may already exist after a partial rollback.
            }
        }

        if (Schema::hasColumn('reservations', 'checkin_hold_expires_at')) {
            try {
                Schema::table('reservations', function (Blueprint $table) {
                    $table->index(['status', 'checkin_hold_expires_at']);
                });
            } catch (Throwable) {
                // The index may already exist after a partial rollback.
            }
        }
    }
};
