<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\User;
use App\Models\RoomType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTestReservations extends Command
{
    protected $signature = 'create:test-reservations {count=3}';

    protected $description = 'Create sample reservations for testing reminders';

    public function handle()
    {
        $count = (int) $this->argument('count');

        // Ensure an admin user exists
        $admin = User::where('role', 'admin')->first();
        if (!$admin) {
            $admin = User::create([
                'name' => 'Test Admin',
                'email' => 'admin@example.test',
                'password' => 'password',
                'role' => 'admin',
            ]);
            $this->info('Created test admin: ' . $admin->email . ' (password: password)');
        } else {
            $this->info('Found existing admin: ' . $admin->email);
        }

        // Ensure a room type exists (schema requires preferred_room_type_id)
        $roomType = RoomType::first();
        if (!$roomType) {
            $roomType = RoomType::create([
                'name' => 'Test Room Type',
                'description' => 'Auto-created for testing',
                'base_rate' => 1000,
                'pricing_type' => 'per_room',
                'is_active' => true,
            ]);
            $this->info('Created test RoomType: ' . $roomType->name);
        }

        $created = 0;
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            // Create reservations with dates spread within next 48 hours
            $offsetHours = ($i * 8); // 0,8,16 hours
            $checkIn = $now->copy()->addHours($offsetHours);
            $checkOut = $checkIn->copy()->addDay();

            $res = Reservation::create([
                'guest_first_name' => 'Test' . Str::random(3),
                'guest_last_name' => 'Guest' . ($i + 1),
                'guest_email' => 'guest+' . time() . $i . '@example.test',
                'guest_phone' => '0900' . rand(100000, 999999),
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'preferred_room_type_id' => $roomType->id,
                'number_of_occupants' => 1,
                'status' => 'approved',
            ]);

            $created++;
            $this->info('Created reservation #' . $res->reference_number . ' for ' . $res->guest_name . ' (check-in ' . $res->check_in_date->toDateString() . ')');
        }

        $this->info("Created {$created} test reservations.");

        return 0;
    }
}
