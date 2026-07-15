<?php

namespace App\Console\Commands;

use App\Models\RoomType;
use App\Services\ResponsiveRoomImageService;
use Illuminate\Console\Command;

class GenerateRoomCardVariants extends Command
{
    protected $signature = 'media:generate-room-card-variants';

    protected $description = 'Generate missing WebP card variants for room type images';

    public function handle(ResponsiveRoomImageService $images): int
    {
        $generated = 0;

        RoomType::query()->select(['id', 'images'])->orderBy('id')->each(function (RoomType $roomType) use ($images, &$generated): void {
            foreach ($roomType->images ?? [] as $path) {
                if (is_string($path) && $path !== '') {
                    $generated += $images->generate($path);
                }
            }
        });

        $this->info("Generated {$generated} room-card image variant(s).");

        return self::SUCCESS;
    }
}
