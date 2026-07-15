<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep the stored image order aligned with the order shown in the room-type editor.
     */
    public function up(): void
    {
        $this->reverseImageOrder();
    }

    public function down(): void
    {
        $this->reverseImageOrder();
    }

    private function reverseImageOrder(): void
    {
        DB::table('room_types')
            ->orderBy('id')
            ->each(function (object $roomType): void {
                $images = json_decode((string) $roomType->images, true);

                if (! is_array($images) || count($images) < 2) {
                    return;
                }

                DB::table('room_types')
                    ->where('id', $roomType->id)
                    ->update([
                        'images' => json_encode(array_values(array_reverse($images)), JSON_UNESCAPED_SLASHES),
                    ]);
            });
    }
};
