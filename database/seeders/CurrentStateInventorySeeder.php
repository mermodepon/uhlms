<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class CurrentStateInventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->users() as $user) {
            User::query()->create($user);
        }

        foreach ($this->settings() as $setting) {
            Setting::query()->create($setting);
        }

        $amenitiesByName = collect();
        foreach ($this->amenities() as $amenity) {
            $record = Amenity::query()->create($amenity);
            $amenitiesByName->put($record->name, $record);
        }

        foreach ($this->services() as $service) {
            Service::query()->create($service);
        }

        $roomTypesByName = collect();
        foreach ($this->roomTypes() as $roomTypeData) {
            $amenityNames = $roomTypeData['amenity_names'];
            unset($roomTypeData['amenity_names']);

            $roomType = RoomType::query()->create($roomTypeData);
            $roomType->amenities()->attach(
                collect($amenityNames)
                    ->map(fn (string $name) => $amenitiesByName->get($name)?->id)
                    ->filter()
                    ->values()
                    ->all()
            );

            $roomTypesByName->put($roomType->name, $roomType);
        }

        $floorsByName = collect();
        foreach ($this->floors() as $floorData) {
            $floor = Floor::query()->create($floorData);
            $floorsByName->put($floor->name, $floor);
        }

        foreach ($this->rooms() as $roomData) {
            Room::query()->create([
                'room_number' => $roomData['room_number'],
                'room_type_id' => $roomTypesByName->get($roomData['room_type_name'])->id,
                'floor_id' => $floorsByName->get($roomData['floor_name'])->id,
                'capacity' => $roomData['capacity'],
                'status' => $roomData['status'],
                'description' => $roomData['description'],
                'notes' => $roomData['notes'],
                'is_active' => $roomData['is_active'],
            ]);
        }
    }

    private function users(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@cmu.edu.ph',
                'password' => '$2y$12$u.XIOVcOVgWLtSGPM6XhYeoPnuvAN8.CA494fTnM/Cages/poO3kG',
                'role' => 'super_admin',
                'permissions' => null,
            ],
            [
                'name' => 'Administrator',
                'email' => 'admin@cmu.edu.ph',
                'password' => '$2y$12$4BmMInCtZyLivs4N5hs9m.37bEBpj1oFZZlxdr1GZfdqYJymV5HWG',
                'role' => 'admin',
                'permissions' => null,
            ],
            [
                'name' => 'Staff',
                'email' => 'staff@cmu.edu.ph',
                'password' => '$2y$12$Ld7oSe3Z65ouu5Nv/utFK.8yQVRoJM9DUZs.MdylMxFyWrXWfYWjW',
                'role' => 'staff',
                'permissions' => null,
            ],
        ];
    }

    private function settings(): array
    {
        return [
            ['key' => 'discount_pwd_percent', 'value' => '20'],
            ['key' => 'discount_senior_percent', 'value' => '20'],
            ['key' => 'discount_student_percent', 'value' => '0'],
            ['key' => 'online_payment_deposit_percentage', 'value' => '30'],
            ['key' => 'online_payments_enabled', 'value' => '0'],
            ['key' => 'signatory_approved_name', 'value' => 'RUBIE ANDOY - ARROYO'],
            ['key' => 'signatory_approved_title', 'value' => 'Director, University Homestay'],
            ['key' => 'signatory_prepared_name', 'value' => 'GENELYN ABARQUEZ – ENSOMO'],
            ['key' => 'signatory_prepared_title', 'value' => 'LODGING SUPERVISOR'],
        ];
    }

    private function amenities(): array
    {
        return [
            ['name' => 'Mini Refrigerator', 'description' => null, 'is_active' => true],
            ['name' => 'Wi-Fi', 'description' => null, 'is_active' => true],
            ['name' => 'Fully Air-Conditioned', 'description' => null, 'is_active' => true],
            ['name' => 'Ceilling Fan', 'description' => null, 'is_active' => true],
            ['name' => 'Common CR', 'description' => null, 'is_active' => true],
            ['name' => 'Private CR', 'description' => null, 'is_active' => true],
        ];
    }

    private function services(): array
    {
        return [
            [
                'name' => 'Extra Towel',
                'code' => 'extra-towel',
                'category' => 'amenity',
                'description' => null,
                'price' => 50.00,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Extra Mattress',
                'code' => 'extra-mattress',
                'category' => 'amenity',
                'description' => null,
                'price' => 400.00,
                'is_active' => true,
                'sort_order' => 0,
            ],
        ];
    }

    private function floors(): array
    {
        return [
            ['name' => '1st Floor', 'level' => 1, 'description' => null, 'is_active' => true],
            ['name' => '2nd Floor', 'level' => 2, 'description' => null, 'is_active' => true],
        ];
    }

    private function roomTypes(): array
    {
        return [
            [
                'name' => 'Deluxe',
                'description' => null,
                'base_rate' => 1200.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Mini Refrigerator', 'Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Executive',
                'description' => null,
                'base_rate' => 1700.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Mini Refrigerator', 'Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Aircon Family (Up-Size)',
                'description' => null,
                'base_rate' => 1200.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Aircon Family',
                'description' => null,
                'base_rate' => 1200.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Aircon Double',
                'description' => null,
                'base_rate' => 1200.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Aircon Single',
                'description' => null,
                'base_rate' => 1200.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Fully Air-Conditioned', 'Private CR'],
            ],
            [
                'name' => 'Dormitory',
                'description' => null,
                'base_rate' => 200.00,
                'pricing_type' => 'per_person',
                'room_sharing_type' => 'public',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Ceilling Fan', 'Common CR'],
            ],
            [
                'name' => 'Non-Aircon Family',
                'description' => null,
                'base_rate' => 1000.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Private CR'],
            ],
            [
                'name' => 'Non-Aircon Family (Up-Size)',
                'description' => null,
                'base_rate' => 1000.00,
                'pricing_type' => 'flat_rate',
                'room_sharing_type' => 'private',
                'images' => [],
                'is_active' => true,
                'amenity_names' => ['Wi-Fi', 'Private CR'],
            ],
        ];
    }

    private function rooms(): array
    {
        return [
            ['room_number' => 'AC 1', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 10', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 15', 'room_type_name' => 'Aircon Double', 'floor_name' => '1st Floor', 'capacity' => 2, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 16', 'room_type_name' => 'Aircon Double', 'floor_name' => '1st Floor', 'capacity' => 2, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 17', 'room_type_name' => 'Aircon Double', 'floor_name' => '1st Floor', 'capacity' => 2, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 18', 'room_type_name' => 'Aircon Double', 'floor_name' => '1st Floor', 'capacity' => 2, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 2', 'room_type_name' => 'Aircon Family (Up-Size)', 'floor_name' => '1st Floor', 'capacity' => 6, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 20', 'room_type_name' => 'Aircon Single', 'floor_name' => '1st Floor', 'capacity' => 1, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 21', 'room_type_name' => 'Aircon Single', 'floor_name' => '1st Floor', 'capacity' => 1, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 23', 'room_type_name' => 'Aircon Single', 'floor_name' => '1st Floor', 'capacity' => 1, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 24', 'room_type_name' => 'Aircon Single', 'floor_name' => '1st Floor', 'capacity' => 1, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 3', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 4', 'room_type_name' => 'Aircon Family (Up-Size)', 'floor_name' => '1st Floor', 'capacity' => 6, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 5', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 6', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 7', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'AC 8', 'room_type_name' => 'Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 1', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 2', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 3', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 4', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 3, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 5', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DLX 7', 'room_type_name' => 'Deluxe', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DRM 201', 'room_type_name' => 'Dormitory', 'floor_name' => '2nd Floor', 'capacity' => 20, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DRM 202', 'room_type_name' => 'Dormitory', 'floor_name' => '2nd Floor', 'capacity' => 20, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DRM 203', 'room_type_name' => 'Dormitory', 'floor_name' => '2nd Floor', 'capacity' => 20, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'DRM 204', 'room_type_name' => 'Dormitory', 'floor_name' => '2nd Floor', 'capacity' => 20, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'EXC 6', 'room_type_name' => 'Executive', 'floor_name' => '1st Floor', 'capacity' => 5, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'EXC 8', 'room_type_name' => 'Executive', 'floor_name' => '1st Floor', 'capacity' => 5, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'NAC 11', 'room_type_name' => 'Non-Aircon Family (Up-Size)', 'floor_name' => '1st Floor', 'capacity' => 6, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'NAC 12', 'room_type_name' => 'Aircon Family (Up-Size)', 'floor_name' => '1st Floor', 'capacity' => 6, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'NAC 14', 'room_type_name' => 'Non-Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
            ['room_number' => 'NAC 9', 'room_type_name' => 'Non-Aircon Family', 'floor_name' => '1st Floor', 'capacity' => 4, 'status' => 'available', 'description' => null, 'notes' => null, 'is_active' => true],
        ];
    }
}
