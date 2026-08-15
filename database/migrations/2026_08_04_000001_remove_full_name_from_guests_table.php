<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('reservation_id');
        });

        DB::table('guests')->update([
            'full_name' => DB::raw("TRIM(CONCAT_WS(' ', NULLIF(TRIM(first_name), ''), NULLIF(TRIM(middle_initial), ''), NULLIF(TRIM(last_name), '')))")
        ]);

        DB::statement('ALTER TABLE guests MODIFY full_name VARCHAR(255) NOT NULL AFTER reservation_id');
    }
};
