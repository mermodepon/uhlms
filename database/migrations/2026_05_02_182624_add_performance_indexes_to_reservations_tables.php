<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Note: reservations table indexes already exist from previous migration attempts
        // Skip duplicate indexes and only add new ones for other tables

        // Add missing composite index to room_assignments table
        if (!$this->indexExistsRaw('room_assignments', 'idx_room_assignments_res_status')) {
            Schema::table('room_assignments', function (Blueprint $table) {
                $table->index(['reservation_id', 'status'], 'idx_room_assignments_res_status');
            });
        }

        // Add missing indexes to reservation_payments table for gateway filtering
        if (!$this->indexExistsRaw('reservation_payments', 'idx_payments_res_gateway_status')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->index(['reservation_id', 'gateway_status'], 'idx_payments_res_gateway_status');
            });
        }

        if (!$this->indexExistsRaw('reservation_payments', 'idx_payments_gateway_type')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->index(['gateway', 'is_deposit', 'gateway_status'], 'idx_payments_gateway_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop added indexes from reservation_payments table
        if ($this->indexExistsRaw('reservation_payments', 'idx_payments_gateway_type')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->dropIndex('idx_payments_gateway_type');
            });
        }

        if ($this->indexExistsRaw('reservation_payments', 'idx_payments_res_gateway_status')) {
            Schema::table('reservation_payments', function (Blueprint $table) {
                $table->dropIndex('idx_payments_res_gateway_status');
            });
        }

        // Drop added index from room_assignments table
        if ($this->indexExistsRaw('room_assignments', 'idx_room_assignments_res_status')) {
            Schema::table('room_assignments', function (Blueprint $table) {
                $table->dropIndex('idx_room_assignments_res_status');
            });
        }
    }

    /**
     * Check if an index exists using raw SQL query (supports both MySQL and SQLite).
     */
    private function indexExistsRaw(string $tableName, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: check sqlite_master table
            $result = DB::select(
                "SELECT COUNT(*) as count FROM sqlite_master 
                 WHERE type = 'index' 
                 AND tbl_name = ? 
                 AND name = ?",
                [$tableName, $indexName]
            );
            return $result[0]->count > 0;
        }

        // MySQL: check information_schema
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = DATABASE() 
             AND table_name = ? 
             AND index_name = ?",
            [$tableName, $indexName]
        );

        return $result[0]->count > 0;
    }
};
