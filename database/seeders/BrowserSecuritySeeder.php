<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class BrowserSecuritySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'browser-admin@example.test';

    public const ADMIN_PASSWORD = 'browser-password';

    public function run(): void
    {
        $database = DB::connection()->getDatabaseName();

        if (! app()->environment('testing') || ! str_ends_with($database, '_testing')) {
            throw new LogicException('Browser security fixtures may only be created in a *_testing database.');
        }

        config()->set('hashing.bcrypt.verify', false);
        config()->set('hashing.argon.verify', false);

        $this->call(CurrentStateInventorySeeder::class);
        $this->call(VirtualTourSeeder::class);

        User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Browser Security Admin',
                'password' => Hash::make(self::ADMIN_PASSWORD),
                'role' => 'super_admin',
                'permissions' => null,
            ],
        );
    }
}
