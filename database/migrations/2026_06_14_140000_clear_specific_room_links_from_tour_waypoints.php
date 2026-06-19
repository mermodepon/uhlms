<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tour_waypoints')
            ->whereNotNull('linked_room_id')
            ->whereNull('linked_room_type_id')
            ->orderBy('id')
            ->select(['id', 'linked_room_id'])
            ->chunkById(100, function ($waypoints): void {
                $roomTypeIdsByRoomId = DB::table('rooms')
                    ->whereIn('id', $waypoints->pluck('linked_room_id')->filter()->unique()->values())
                    ->pluck('room_type_id', 'id');

                foreach ($waypoints as $waypoint) {
                    $roomTypeId = $roomTypeIdsByRoomId[$waypoint->linked_room_id] ?? null;

                    if (! $roomTypeId) {
                        continue;
                    }

                    DB::table('tour_waypoints')
                        ->where('id', $waypoint->id)
                        ->update(['linked_room_type_id' => $roomTypeId]);
                }
            });

        DB::table('tour_waypoints')
            ->whereNotNull('linked_room_id')
            ->update(['linked_room_id' => null]);
    }

    public function down(): void
    {
        // Specific room links cannot be restored after being converted to room type links.
    }
};
