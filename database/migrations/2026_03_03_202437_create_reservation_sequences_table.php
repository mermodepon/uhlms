<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_sequence')->default(0);
        });

        // Seed with the current highest sequence per year from existing (and previously deleted) reservations.
        // We pull the max numeric suffix from the reservations table so we start above all ever-used numbers.
        $rows = DB::table('reservations')
            ->selectRaw("CAST(SUBSTRING_INDEX(reference_number, '-', 1) AS UNSIGNED) as yr,
                         MAX(CAST(SUBSTRING_INDEX(reference_number, '-', -1) AS UNSIGNED)) as max_seq")
            ->whereRaw("reference_number REGEXP '^[0-9]{4}-[0-9]+$'")
            ->groupBy('yr')
            ->get();

        foreach ($rows as $row) {
            DB::table('reservation_sequences')->insertOrIgnore([
                'year'          => $row->yr,
                'last_sequence' => $row->max_seq,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_sequences');
    }
};
