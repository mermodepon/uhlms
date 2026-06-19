<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tour_hotspots MODIFY action_type ENUM('info','navigate','bookmark','external-link','previous-scene') NOT NULL DEFAULT 'info'");
    }

    public function down(): void
    {
        DB::table('tour_hotspots')
            ->where('action_type', 'previous-scene')
            ->update([
                'action_type' => 'info',
                'action_target' => null,
            ]);

        DB::statement("ALTER TABLE tour_hotspots MODIFY action_type ENUM('info','navigate','bookmark','external-link') NOT NULL DEFAULT 'info'");
    }
};
