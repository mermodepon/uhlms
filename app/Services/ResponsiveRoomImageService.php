<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ResponsiveRoomImageService
{
    private const CARD_WIDTHS = [480, 960];

    public function generate(string $path): int
    {
        $disk = Storage::disk(config('media.disk'));

        if (! $disk->exists($path) || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return 0;
        }

        $contents = $disk->get($path);
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return 0;
        }

        $generated = 0;
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        foreach (self::CARD_WIDTHS as $width) {
            $variantPath = $this->variantPath($path, $width);

            if ($disk->exists($variantPath) || $sourceWidth <= 0 || $sourceHeight <= 0) {
                continue;
            }

            $targetWidth = min($width, $sourceWidth);
            $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));
            $variant = imagecreatetruecolor($targetWidth, $targetHeight);

            imagealphablending($variant, false);
            imagesavealpha($variant, true);
            imagefill($variant, 0, 0, imagecolorallocatealpha($variant, 0, 0, 0, 127));
            imagecopyresampled($variant, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            ob_start();
            imagewebp($variant, null, 82);
            $encoded = ob_get_clean();
            imagedestroy($variant);

            if (is_string($encoded) && $encoded !== '') {
                $disk->put($variantPath, $encoded, ['visibility' => 'public']);
                $generated++;
            }
        }

        imagedestroy($source);

        return $generated;
    }

    public function variantPath(string $path, int $width): string
    {
        return pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME).".card-{$width}.webp";
    }

    /**
     * @return array<int, string>
     */
    public function cardSources(string $path): array
    {
        $disk = Storage::disk(config('media.disk'));
        $sources = [];

        foreach (self::CARD_WIDTHS as $width) {
            $variantPath = $this->variantPath($path, $width);

            if (! $disk->exists($variantPath)) {
                return [];
            }

            $sources[$width] = $variantPath;
        }

        return $sources;
    }
}
