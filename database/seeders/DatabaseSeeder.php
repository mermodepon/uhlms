<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\RoomType;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Amenity;
use App\Models\Service;
use App\Models\Reservation;
use App\Models\RoomAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Users (Admin & Staff only)
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@cmu.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $staff1 = User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@cmu.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        $staff2 = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@cmu.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        // Create Amenities
        $amenities = collect([
            ['name' => 'WiFi', 'description' => 'High-speed wireless internet'],
            ['name' => 'Air Conditioning', 'description' => 'Individual climate control'],
            ['name' => 'Private Bathroom', 'description' => 'En-suite bathroom with hot water'],
            ['name' => 'Television', 'description' => 'Flat-screen cable TV'],
            ['name' => 'Mini Refrigerator', 'description' => 'Small refrigerator in room'],
            ['name' => 'Study Desk', 'description' => 'Work/study desk with lamp'],
            ['name' => 'Closet/Wardrobe', 'description' => 'Clothing storage space'],
            ['name' => 'Hot Water', 'description' => 'Hot water shower'],
            ['name' => 'Towels & Linens', 'description' => 'Fresh towels and bed linens provided'],
            ['name' => 'Fan', 'description' => 'Electric fan'],
        ])->map(fn ($a) => Amenity::create($a));

        // Create Services
        $services = collect([
            [
                'name' => 'Extra Towels', 
                'code' => 'extra_towels', 
                'category' => 'amenity', 
                'description' => 'Additional bath towels for the room',
                'price' => 0.00,
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Extra Bed', 
                'code' => 'extra_bed', 
                'category' => 'amenity', 
                'description' => 'Additional bed/mattress for extra guest',
                'price' => 200.00,
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Iron Rental', 
                'code' => 'iron_rental', 
                'category' => 'equipment', 
                'description' => 'Iron and ironing board rental',
                'price' => 50.00,
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Extra Blanket', 
                'code' => 'extra_blanket', 
                'category' => 'amenity', 
                'description' => 'Additional blanket for cold weather',
                'price' => 0.00,
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Extra Pillow', 
                'code' => 'extra_pillow', 
                'category' => 'amenity', 
                'description' => 'Additional pillow for comfort',
                'price' => 0.00,
                'is_active' => true,
                'sort_order' => 5
            ],
            [
                'name' => 'Hair Dryer', 
                'code' => 'hair_dryer', 
                'category' => 'equipment', 
                'description' => 'Hair dryer rental',
                'price' => 30.00,
                'is_active' => true,
                'sort_order' => 6
            ],
            [
                'name' => 'Early Check-in', 
                'code' => 'early_checkin', 
                'category' => 'special_service', 
                'description' => 'Check-in before standard time (additional fee)',
                'price' => 300.00,
                'is_active' => true,
                'sort_order' => 7
            ],
            [
                'name' => 'Late Check-out', 
                'code' => 'late_checkout', 
                'category' => 'special_service', 
                'description' => 'Check-out after standard time (additional fee)',
                'price' => 300.00,
                'is_active' => true,
                'sort_order' => 8
            ],
        ])->map(fn ($s) => Service::create($s));

        // Create Room Types
        $standard = RoomType::create([
            'name' => 'Standard Room',
            'description' => 'A comfortable single-occupancy room ideal for visiting scholars and solo travelers. Features basic amenities for a pleasant stay at CMU.',
            'base_rate' => 800.00,
            'pricing_type' => 'flat_rate',
            'is_active' => true,
        ]);
        $standard->amenities()->attach([$amenities[0]->id, $amenities[5]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id, $amenities[9]->id]);

        $deluxe = RoomType::create([
            'name' => 'Deluxe Room',
            'description' => 'An upgraded room with air conditioning and additional amenities. Perfect for faculty guests and visiting professionals.',
            'base_rate' => 1500.00,
            'pricing_type' => 'flat_rate',
            'is_active' => true,
        ]);
        $deluxe->amenities()->attach([$amenities[0]->id, $amenities[1]->id, $amenities[2]->id, $amenities[3]->id, $amenities[5]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id]);

        $suite = RoomType::create([
            'name' => 'Suite',
            'description' => 'A spacious suite with separate living area. Suitable for VIP guests, university officials, and longer stays.',
            'base_rate' => 2500.00,
            'pricing_type' => 'flat_rate',
            'is_active' => true,
        ]);
        $suite->amenities()->attach($amenities->pluck('id'));

        $family = RoomType::create([
            'name' => 'Family Room',
            'description' => 'A large room designed for families or small groups attending university events. Features multiple beds and ample space.',
            'base_rate' => 2000.00,
            'pricing_type' => 'flat_rate',
            'is_active' => true,
        ]);
        $family->amenities()->attach([$amenities[0]->id, $amenities[1]->id, $amenities[2]->id, $amenities[3]->id, $amenities[4]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id]);

        $dormitory = RoomType::create([
            'name' => 'Dormitory',
            'description' => 'Shared dormitory-style accommodation with bunk beds. Ideal for student groups and budget-conscious travelers.',
            'base_rate' => 350.00,
            'pricing_type' => 'per_person',
            'is_active' => true,
        ]);
        $dormitory->amenities()->attach([$amenities[0]->id, $amenities[6]->id, $amenities[8]->id, $amenities[9]->id]);

        // Create Floors
        $floors = collect([
            Floor::create(['name' => 'Ground Floor', 'level' => 1, 'description' => 'Reception and standard rooms', 'is_active' => true]),
            Floor::create(['name' => '2nd Floor', 'level' => 2, 'description' => 'Deluxe rooms', 'is_active' => true]),
            Floor::create(['name' => '3rd Floor', 'level' => 3, 'description' => 'Suites and family rooms', 'is_active' => true]),
        ]);

        // Create Rooms
        $rooms = collect();

        // Ground Floor - Standard rooms
        foreach (['101', '102', '103', '104', '105'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $standard->id,
                'floor_id' => $floors[0]->id,
                'status' => 'available',
                'is_active' => true,
            ]));
        }
        // Ground Floor - Dormitory
        $rooms->push(Room::create([
            'room_number' => '106',
            'room_type_id' => $dormitory->id,
            'floor_id' => $floors[0]->id,
            'status' => 'available',
            'is_active' => true,
        ]));
        $rooms->push(Room::create([
            'room_number' => '107',
            'room_type_id' => $dormitory->id,
            'floor_id' => $floors[0]->id,
            'status' => 'available',
            'is_active' => true,
        ]));

        // 2nd Floor - Deluxe rooms
        foreach (['201', '202', '203', '204', '205'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $deluxe->id,
                'floor_id' => $floors[1]->id,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // 3rd Floor - Suite & Family
        foreach (['301', '302'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $suite->id,
                'floor_id' => $floors[2]->id,
                'status' => 'available',
                'is_active' => true,
            ]));
        }
        foreach (['303', '304'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $family->id,
                'floor_id' => $floors[2]->id,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // Set one room to maintenance
        $rooms[2]->update(['status' => 'maintenance', 'notes' => 'Plumbing repair in progress']);

        // Set one room to inactive
        $rooms[6]->update(['status' => 'inactive', 'is_active' => false, 'notes' => 'Closed for renovation']);

        // Create Sample Reservations

        // 1. Checked-out reservation (completed stay)
        $res1 = Reservation::create([
            'reference_number' => '2026-0001',
            'guest_last_name' => 'Rizal',
            'guest_first_name' => 'Jose',
            'guest_middle_initial' => 'P.',
            'guest_email' => 'jose.rizal@email.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Calamba, Laguna',
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today()->subDays(7),
            'check_out_date' => Carbon::today()->subDays(4),
            'number_of_occupants' => 1,
            'purpose' => 'academic',
            'status' => 'checked_out',
            'admin_notes' => 'VIP visiting professor',
            'reviewed_by' => $staff1->id,
            'reviewed_at' => Carbon::today()->subDays(10),
        ]);
        $ra1 = RoomAssignment::create([
            'reservation_id' => $res1->id,
            'room_id' => $rooms[7]->id, // Room 201
            'assigned_by' => $staff1->id,
            'assigned_at' => Carbon::today()->subDays(8),
            'checked_in_at' => Carbon::today()->subDays(7)->setTime(14, 0),
            'checked_in_by' => $staff1->id,
            'checked_out_at' => Carbon::today()->subDays(4)->setTime(10, 30),
            'checked_out_by' => $staff2->id,
            'remarks' => 'Smooth check-in and check-out.',
        ]);

        // 2. Currently checked in
        $res2 = Reservation::create([
            'reference_number' => '2026-0002',
            'guest_last_name' => 'Silang',
            'guest_first_name' => 'Gabriela',
            'guest_middle_initial' => 'C.',
            'guest_email' => 'gabriela.s@email.com',
            'guest_phone' => '09191234567',
            'guest_address' => 'Ilocos Sur',
            'preferred_room_type_id' => $suite->id,
            'check_in_date' => Carbon::today()->subDays(2),
            'check_out_date' => Carbon::today()->addDays(3),
            'number_of_occupants' => 2,
            'purpose' => 'official',
            'special_requests' => 'Need extra pillows please',
            'status' => 'checked_in',
            'admin_notes' => 'Government official visit',
            'reviewed_by' => $admin->id,
            'reviewed_at' => Carbon::today()->subDays(5),
        ]);
        $ra2 = RoomAssignment::create([
            'reservation_id' => $res2->id,
            'room_id' => $rooms[12]->id, // Room 301
            'assigned_by' => $admin->id,
            'assigned_at' => Carbon::today()->subDays(3),
            'checked_in_at' => Carbon::today()->subDays(2)->setTime(15, 0),
            'checked_in_by' => $staff1->id,
            'remarks' => 'Guest arrived with 2 occupants, extra pillows provided.',
        ]);
        $rooms[12]->update(['status' => 'occupied']);

        // 3. Currently checked in (standard room)
        $res3 = Reservation::create([
            'reference_number' => '2026-0003',
            'guest_last_name' => 'Bonifacio',
            'guest_first_name' => 'Andres',
            'guest_middle_initial' => 'B.',
            'guest_email' => 'andres.b@email.com',
            'guest_phone' => '09181234567',
            'guest_address' => 'Tondo, Manila',
            'preferred_room_type_id' => $standard->id,
            'check_in_date' => Carbon::today()->subDay(),
            'check_out_date' => Carbon::today()->addDays(2),
            'number_of_occupants' => 1,
            'purpose' => 'personal',
            'status' => 'checked_in',
            'reviewed_by' => $staff2->id,
            'reviewed_at' => Carbon::today()->subDays(3),
        ]);
        RoomAssignment::create([
            'reservation_id' => $res3->id,
            'room_id' => $rooms[0]->id, // Room 101
            'assigned_by' => $staff2->id,
            'assigned_at' => Carbon::today()->subDays(2),
            'checked_in_at' => Carbon::today()->subDay()->setTime(13, 30),
            'checked_in_by' => $staff2->id,
        ]);
        $rooms[0]->update(['status' => 'occupied']);

        // 4. Approved, awaiting check-in (arriving today)
        Reservation::create([
            'reference_number' => '2026-0004',
            'guest_last_name' => 'Mabini',
            'guest_first_name' => 'Apolinario',
            'guest_middle_initial' => 'M.',
            'guest_email' => 'apolinario.m@email.com',
            'guest_phone' => '09201234567',
            'guest_address' => 'Tanauan, Batangas',
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(3),
            'number_of_occupants' => 1,
            'purpose' => 'academic',
            'status' => 'approved',
            'admin_notes' => 'Guest researcher, assign Room 202 if available',
            'reviewed_by' => $staff1->id,
            'reviewed_at' => Carbon::today()->subDays(2),
        ]);

        // 5. Approved, future arrival
        Reservation::create([
            'reference_number' => '2026-0005',
            'guest_last_name' => 'Magbanua',
            'guest_first_name' => 'Teresa',
            'guest_middle_initial' => 'F.',
            'guest_email' => 'teresa.m@email.com',
            'guest_phone' => '09231234567',
            'guest_address' => 'Iloilo City',
            'preferred_room_type_id' => $family->id,
            'check_in_date' => Carbon::today()->addDays(5),
            'check_out_date' => Carbon::today()->addDays(8),
            'number_of_occupants' => 4,
            'purpose' => 'event',
            'special_requests' => 'Attending CMU Foundation Anniversary',
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => Carbon::today()->subDay(),
        ]);

        // 6. Pending review
        Reservation::create([
            'reference_number' => '2026-0006',
            'guest_last_name' => 'Aquino',
            'guest_first_name' => 'Melchora',
            'guest_middle_initial' => 'D.',
            'guest_email' => 'melchora.a@email.com',
            'guest_phone' => '09211234567',
            'guest_address' => 'Caloocan City',
            'preferred_room_type_id' => $standard->id,
            'check_in_date' => Carbon::today()->addDays(7),
            'check_out_date' => Carbon::today()->addDays(10),
            'number_of_occupants' => 1,
            'purpose' => 'personal',
            'status' => 'pending',
        ]);

        // 7. Pending review
        Reservation::create([
            'reference_number' => '2026-0007',
            'guest_last_name' => 'Aguinaldo',
            'guest_first_name' => 'Emilio',
            'guest_middle_initial' => 'F.',
            'guest_email' => 'emilio.a@email.com',
            'guest_phone' => '09221234567',
            'guest_address' => 'Kawit, Cavite',
            'preferred_room_type_id' => $suite->id,
            'check_in_date' => Carbon::today()->addDays(10),
            'check_out_date' => Carbon::today()->addDays(12),
            'number_of_occupants' => 2,
            'purpose' => 'official',
            'special_requests' => 'Need projector for presentation',
            'status' => 'pending',
        ]);

        // 8. Pending review
        Reservation::create([
            'reference_number' => '2026-0008',
            'guest_last_name' => 'Luna',
            'guest_first_name' => 'Antonio',
            'guest_middle_initial' => 'N.',
            'guest_email' => 'antonio.l@email.com',
            'guest_address' => 'Binondo, Manila',
            'preferred_room_type_id' => $dormitory->id,
            'check_in_date' => Carbon::today()->addDays(3),
            'check_out_date' => Carbon::today()->addDays(5),
            'number_of_occupants' => 6,
            'purpose' => 'academic',
            'special_requests' => 'Student research group from Mindanao State University',
            'status' => 'pending',
        ]);

        // 9. Declined
        Reservation::create([
            'reference_number' => '2026-0009',
            'guest_last_name' => 'Del Pilar',
            'guest_first_name' => 'Gregorio',
            'guest_middle_initial' => 'H.',
            'guest_email' => 'gregorio.dp@email.com',
            'guest_address' => 'Bulacan',
            'preferred_room_type_id' => $suite->id,
            'check_in_date' => Carbon::today()->addDays(1),
            'check_out_date' => Carbon::today()->addDays(4),
            'number_of_occupants' => 3,
            'purpose' => 'personal',
            'status' => 'declined',
            'admin_notes' => 'All suites are fully booked for the requested dates. Please try alternative dates or room type.',
            'reviewed_by' => $staff1->id,
            'reviewed_at' => Carbon::today()->subDay(),
        ]);

        // 10. Cancelled
        Reservation::create([
            'reference_number' => '2026-0010',
            'guest_last_name' => 'Tecson',
            'guest_first_name' => 'Trinidad',
            'guest_middle_initial' => 'P.',
            'guest_email' => 'trinidad.t@email.com',
            'guest_phone' => '09261234567',
            'guest_address' => 'San Miguel, Bulacan',
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today()->addDays(2),
            'check_out_date' => Carbon::today()->addDays(5),
            'number_of_occupants' => 1,
            'purpose' => 'academic',
            'status' => 'cancelled',
            'admin_notes' => 'Guest requested cancellation due to change in travel plans.',
            'reviewed_by' => $staff2->id,
            'reviewed_at' => Carbon::today(),
        ]);
    }
}
