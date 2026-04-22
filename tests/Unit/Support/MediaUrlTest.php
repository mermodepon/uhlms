<?php

namespace Tests\Unit\Support;

use App\Support\MediaUrl;
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
}
