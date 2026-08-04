<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'guest_reservation_steps_heading')
            ->where('value', 'How to Reserve')
            ->update(['value' => 'How to Request']);

        Cache::forget('setting_guest_reservation_steps_heading');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'guest_reservation_steps_heading')
            ->where('value', 'How to Request')
            ->update(['value' => 'How to Reserve']);

        Cache::forget('setting_guest_reservation_steps_heading');
    }
};
