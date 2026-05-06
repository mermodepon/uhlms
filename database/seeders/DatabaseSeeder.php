<?php

namespace Database\Seeders;

use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            $this->truncateTables();

            $this->call([
                CurrentStateInventorySeeder::class,
                ReservationDemoSeeder::class,
            ]);

            DB::table('reservation_sequences')->updateOrInsert(
                ['year' => Carbon::today()->year],
                ['last_sequence' => 100],
            );

            Room::query()->each(function (Room $room): void {
                $room->recalculateStatus();
            });
        });
    }

    private function truncateTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ([
            'notifications',
            'force_deletion_logs',
            'check_in_snapshots',
            'reservation_payments',
            'reservation_charges',
            'room_holds',
            'room_assignments',
            'guests',
            'reservation_logs',
            'reservations',
            'tour_hotspots',
            'tour_waypoints',
            'rooms',
            'floors',
            'amenity_room_type',
            'room_types',
            'services',
            'amenities',
            'settings',
            'users',
            'reservation_sequences',
        ] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
