<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public Media Disk
    |--------------------------------------------------------------------------
    |
    | Guest-facing media such as room photos, virtual tour panoramas, and
    | uploaded hotspot assets should use a persistent public disk. Keep this
    | set to "public" for local/XAMPP development and switch it to "s3" when
    | deploying to Laravel Cloud with object storage enabled.
    |
    */

    'disk' => env('PUBLIC_MEDIA_DISK', 'public'),

];
