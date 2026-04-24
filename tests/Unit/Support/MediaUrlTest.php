<?php

namespace Tests\Unit\Support;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_it_uses_the_configured_media_disk(): void
    {
        config(['media.disk' => 's3']);

        $this->assertSame('s3', MediaUrl::disk());
    }

    public function test_it_returns_null_for_blank_paths(): void
    {
        $this->assertNull(MediaUrl::url(null));
        $this->assertNull(MediaUrl::url(''));
    }

    public function test_it_returns_root_relative_urls_for_the_public_disk(): void
    {
        config(['media.disk' => 'public']);

        $this->assertSame(
            '/storage/virtual-tour/panoramas/entrance.jpg',
            MediaUrl::url('virtual-tour/panoramas/entrance.jpg')
        );
    }

    public function test_it_uses_storage_urls_for_non_public_disks(): void
    {
        config(['media.disk' => 'cdn']);

        Storage::shouldReceive('disk')
            ->once()
            ->with('cdn')
            ->andReturn(new class {
                public function url(string $path): string
                {
                    return 'https://media.example.test/'.$path;
                }
            });

        $this->assertSame(
            'https://media.example.test/virtual-tour/panoramas/entrance.jpg',
            MediaUrl::url('virtual-tour/panoramas/entrance.jpg')
        );
    }
}
