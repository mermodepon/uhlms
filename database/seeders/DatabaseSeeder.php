<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\CheckInSnapshot;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationCharge;
use App\Models\ReservationPayment;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Users ────────────────────────────────────────────────────────
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@cmu.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

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

        $staff3 = User::create([
            'name' => 'Elena Reyes',
            'email' => 'elena@cmu.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        // ─── Settings ────────────────────────────────────────────────────
        $settings = [
            'discount_pwd_percent' => '20',
            'discount_senior_percent' => '20',
            'discount_student_percent' => '10',
            'signatory_prepared_name' => 'GENELYN ABARQUEZ – ENSOMO',
            'signatory_prepared_title' => 'LODGING SUPERVISOR',
            'signatory_approved_name' => 'RUBIE ANDOY - ARROYO',
            'signatory_approved_title' => 'Director, University Homestay',
        ];
        foreach ($settings as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        // ─── Amenities ───────────────────────────────────────────────────
        $amenities = collect([
            ['name' => 'WiFi',              'description' => 'High-speed wireless internet'],
            ['name' => 'Air Conditioning',  'description' => 'Individual climate control'],
            ['name' => 'Private Bathroom',  'description' => 'En-suite bathroom with hot water'],
            ['name' => 'Television',        'description' => 'Flat-screen cable TV'],
            ['name' => 'Mini Refrigerator', 'description' => 'Small refrigerator in room'],
            ['name' => 'Study Desk',        'description' => 'Work/study desk with lamp'],
            ['name' => 'Closet/Wardrobe',   'description' => 'Clothing storage space'],
            ['name' => 'Hot Water',         'description' => 'Hot water shower'],
            ['name' => 'Towels & Linens',   'description' => 'Fresh towels and bed linens provided'],
            ['name' => 'Fan',               'description' => 'Electric fan'],
        ])->map(fn ($a) => Amenity::create($a));

        // ─── Services / Add-Ons ──────────────────────────────────────────
        $services = collect([
            ['name' => 'Extra Towels',   'code' => 'extra_towels',   'category' => 'amenity',         'description' => 'Additional bath towels for the room',             'price' => 0.00,   'sort_order' => 1],
            ['name' => 'Extra Bed',      'code' => 'extra_bed',      'category' => 'amenity',         'description' => 'Additional bed/mattress for extra guest',         'price' => 200.00, 'sort_order' => 2],
            ['name' => 'Iron Rental',    'code' => 'iron_rental',    'category' => 'equipment',       'description' => 'Iron and ironing board rental',                   'price' => 50.00,  'sort_order' => 3],
            ['name' => 'Extra Blanket',  'code' => 'extra_blanket',  'category' => 'amenity',         'description' => 'Additional blanket for cold weather',             'price' => 0.00,   'sort_order' => 4],
            ['name' => 'Extra Pillow',   'code' => 'extra_pillow',   'category' => 'amenity',         'description' => 'Additional pillow for comfort',                   'price' => 0.00,   'sort_order' => 5],
            ['name' => 'Hair Dryer',     'code' => 'hair_dryer',     'category' => 'equipment',       'description' => 'Hair dryer rental',                               'price' => 30.00,  'sort_order' => 6],
            ['name' => 'Early Check-in', 'code' => 'early_checkin',  'category' => 'special_service', 'description' => 'Check-in before standard time (additional fee)',   'price' => 300.00, 'sort_order' => 7],
            ['name' => 'Late Check-out', 'code' => 'late_checkout',  'category' => 'special_service', 'description' => 'Check-out after standard time (additional fee)',   'price' => 300.00, 'sort_order' => 8],
        ])->map(fn ($s) => Service::create(array_merge($s, ['is_active' => true])));

        // ─── Room Types ──────────────────────────────────────────────────
        $standard = RoomType::create([
            'name' => 'Standard Room',
            'description' => 'A comfortable single-occupancy room ideal for visiting scholars and solo travelers. Features basic amenities for a pleasant stay at CMU.',
            'base_rate' => 800.00,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $standard->amenities()->attach([$amenities[0]->id, $amenities[5]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id, $amenities[9]->id]);

        $deluxe = RoomType::create([
            'name' => 'Deluxe Room',
            'description' => 'An upgraded room with air conditioning and additional amenities. Perfect for faculty guests and visiting professionals.',
            'base_rate' => 1500.00,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $deluxe->amenities()->attach([$amenities[0]->id, $amenities[1]->id, $amenities[2]->id, $amenities[3]->id, $amenities[5]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id]);

        $suite = RoomType::create([
            'name' => 'Suite',
            'description' => 'A spacious suite with separate living area. Suitable for VIP guests, university officials, and longer stays.',
            'base_rate' => 2500.00,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $suite->amenities()->attach($amenities->pluck('id'));

        $family = RoomType::create([
            'name' => 'Family Room',
            'description' => 'A large room designed for families or small groups attending university events. Features multiple beds and ample space.',
            'base_rate' => 2000.00,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);
        $family->amenities()->attach([$amenities[0]->id, $amenities[1]->id, $amenities[2]->id, $amenities[3]->id, $amenities[4]->id, $amenities[6]->id, $amenities[7]->id, $amenities[8]->id]);

        $dormitory = RoomType::create([
            'name' => 'Dormitory',
            'description' => 'Shared dormitory-style accommodation with bunk beds. Ideal for student groups and budget-conscious travelers.',
            'base_rate' => 350.00,
            'pricing_type' => 'per_person',
            'room_sharing_type' => 'public',
            'is_active' => true,
        ]);
        $dormitory->amenities()->attach([$amenities[0]->id, $amenities[6]->id, $amenities[8]->id, $amenities[9]->id]);

        // ─── Floors ──────────────────────────────────────────────────────
        $floors = collect([
            Floor::create(['name' => 'Ground Floor', 'level' => 1, 'description' => 'Reception and standard rooms', 'is_active' => true]),
            Floor::create(['name' => '2nd Floor',    'level' => 2, 'description' => 'Deluxe rooms',                 'is_active' => true]),
            Floor::create(['name' => '3rd Floor',    'level' => 3, 'description' => 'Suites and family rooms',      'is_active' => true]),
        ]);

        // ─── Rooms ───────────────────────────────────────────────────────
        $rooms = collect();

        // Ground Floor – Standard rooms (capacity 2)
        foreach (['101', '102', '103', '104', '105'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $standard->id,
                'floor_id' => $floors[0]->id,
                'capacity' => 2,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // Ground Floor – Dormitory rooms (capacity 8)
        $rooms->push(Room::create([
            'room_number' => '106',
            'room_type_id' => $dormitory->id,
            'floor_id' => $floors[0]->id,
            'capacity' => 8,
            'status' => 'available',
            'is_active' => true,
        ]));
        $rooms->push(Room::create([
            'room_number' => '107',
            'room_type_id' => $dormitory->id,
            'floor_id' => $floors[0]->id,
            'capacity' => 8,
            'status' => 'available',
            'is_active' => true,
        ]));

        // 2nd Floor – Deluxe rooms (capacity 2)
        foreach (['201', '202', '203', '204', '205'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $deluxe->id,
                'floor_id' => $floors[1]->id,
                'capacity' => 2,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // 3rd Floor – Suites (capacity 3)
        foreach (['301', '302'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $suite->id,
                'floor_id' => $floors[2]->id,
                'capacity' => 3,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // 3rd Floor – Family rooms (capacity 5)
        foreach (['303', '304'] as $num) {
            $rooms->push(Room::create([
                'room_number' => $num,
                'room_type_id' => $family->id,
                'floor_id' => $floors[2]->id,
                'capacity' => 5,
                'status' => 'available',
                'is_active' => true,
            ]));
        }

        // Room index reference:
        // 0=101, 1=102, 2=103, 3=104, 4=105, 5=106(dorm), 6=107(dorm)
        // 7=201, 8=202, 9=203, 10=204, 11=205
        // 12=301, 13=302, 14=303, 15=304

        // Set one room to maintenance
        $rooms[2]->update(['status' => 'maintenance', 'notes' => 'Plumbing repair in progress']);

        // Set one room to inactive
        $rooms[6]->update(['status' => 'inactive', 'is_active' => false, 'notes' => 'Closed for renovation']);

        // ─── Reservations ────────────────────────────────────────────────

        // === 1. Checked-out reservation (completed stay – solo) ==========
        $res1 = Reservation::create([
            'reference_number' => '2026-0001',
            'guest_last_name' => 'Rizal',
            'guest_first_name' => 'Jose',
            'guest_middle_initial' => 'P.',
            'guest_email' => 'jose.rizal@email.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Calamba, Laguna',
            'guest_gender' => 'Male',
            'guest_age' => 35,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today()->subDays(7),
            'check_out_date' => Carbon::today()->subDays(4),
            'number_of_occupants' => 1,
            'purpose' => 'academic',
            'status' => 'checked_out',
            'admin_notes' => 'VIP visiting professor',
            'reviewed_by' => $staff1->id,
            'reviewed_at' => Carbon::today()->subDays(10),
            'addons_total' => 0.00,
            'payments_total' => 4500.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
        ]);

        $guest1 = Guest::create([
            'reservation_id' => $res1->id,
            'full_name' => 'Jose P. Rizal',
            'first_name' => 'Jose',
            'last_name' => 'Rizal',
            'middle_initial' => 'P.',
            'relationship_to_primary' => 'self',
            'age' => 35,
            'gender' => 'Male',
            'contact_number' => '09171234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260001',
        ]);
        $res1->update(['billing_guest_id' => $guest1->id]);

        RoomAssignment::create([
            'reservation_id' => $res1->id,
            'guest_id' => $guest1->id,
            'room_id' => $rooms[7]->id, // Room 201
            'assigned_by' => $staff1->id,
            'assigned_at' => Carbon::today()->subDays(8),
            'checked_in_at' => Carbon::today()->subDays(7)->setTime(14, 0),
            'checked_in_by' => $staff1->id,
            'checked_out_at' => Carbon::today()->subDays(4)->setTime(10, 30),
            'checked_out_by' => $staff2->id,
            'status' => 'checked_out',
            'guest_last_name' => 'Rizal',
            'guest_first_name' => 'Jose',
            'guest_middle_initial' => 'P.',
            'guest_gender' => 'Male',
            'guest_age' => 35,
            'guest_full_address' => 'Calamba, Laguna',
            'guest_contact_number' => '09171234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260001',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'detailed_checkin_datetime' => Carbon::today()->subDays(7)->setTime(14, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(4)->setTime(10, 30),
            'payment_mode' => 'Cash',
            'payment_amount' => 4500.00,
            'payment_or_number' => 'OR-2026-0001',
            'or_date' => Carbon::today()->subDays(7)->toDateString(),
            'remarks' => 'Smooth check-in and check-out.',
        ]);

        ReservationCharge::create([
            'reservation_id' => $res1->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Deluxe Room – 3 nights × ₱1,500.00',
            'qty' => 3,
            'unit_price' => 1500.00,
            'amount' => 4500.00,
            'created_by' => $staff1->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res1->id,
            'amount' => 4500.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0001',
            'or_date' => Carbon::today()->subDays(7)->toDateString(),
            'status' => 'posted',
            'received_by' => $staff1->id,
            'received_at' => Carbon::today()->subDays(7)->setTime(14, 0),
            'remarks' => 'Full payment on check-in',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res1->id,
            'guest_id' => $guest1->id,
            'id_type' => 'National ID',
            'id_number' => 'NID-20260001',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'detailed_checkin_datetime' => Carbon::today()->subDays(7)->setTime(14, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(4)->setTime(10, 30),
            'payment_mode' => 'Cash',
            'payment_amount' => 4500.00,
            'payment_or_number' => 'OR-2026-0001',
            'or_date' => Carbon::today()->subDays(7)->toDateString(),
            'additional_requests' => [],
            'remarks' => 'Completed stay – no issues',
            'captured_by' => $staff1->id,
            'captured_at' => Carbon::today()->subDays(7)->setTime(14, 0),
        ]);

        // === 2. Checked-in (suite – 2 occupants, with companion) =========
        $res2 = Reservation::create([
            'reference_number' => '2026-0002',
            'guest_last_name' => 'Silang',
            'guest_first_name' => 'Gabriela',
            'guest_middle_initial' => 'C.',
            'guest_email' => 'gabriela.s@email.com',
            'guest_phone' => '09191234567',
            'guest_address' => 'Santa, Ilocos Sur',
            'guest_gender' => 'Female',
            'guest_age' => 42,
            'num_male_guests' => 1,
            'num_female_guests' => 1,
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
            'addons_total' => 200.00,
            'payments_total' => 6000.00,
            'balance_due' => 6700.00,
            'payment_status' => 'partially_paid',
        ]);

        $guest2a = Guest::create([
            'reservation_id' => $res2->id,
            'full_name' => 'Gabriela C. Silang',
            'first_name' => 'Gabriela',
            'last_name' => 'Silang',
            'middle_initial' => 'C.',
            'relationship_to_primary' => 'self',
            'age' => 42,
            'gender' => 'Female',
            'contact_number' => '09191234567',
            'id_type' => 'Passport',
            'id_number' => 'PP-2026-0002',
        ]);
        $guest2b = Guest::create([
            'reservation_id' => $res2->id,
            'full_name' => 'Diego S. Silang',
            'first_name' => 'Diego',
            'last_name' => 'Silang',
            'middle_initial' => 'S.',
            'relationship_to_primary' => 'Spouse',
            'age' => 45,
            'gender' => 'Male',
            'contact_number' => '09191234568',
        ]);
        $res2->update(['billing_guest_id' => $guest2a->id]);

        RoomAssignment::create([
            'reservation_id' => $res2->id,
            'guest_id' => $guest2a->id,
            'room_id' => $rooms[12]->id, // Room 301
            'assigned_by' => $admin->id,
            'assigned_at' => Carbon::today()->subDays(3),
            'checked_in_at' => Carbon::today()->subDays(2)->setTime(15, 0),
            'checked_in_by' => $staff1->id,
            'status' => 'checked_in',
            'guest_last_name' => 'Silang',
            'guest_first_name' => 'Gabriela',
            'guest_middle_initial' => 'C.',
            'guest_gender' => 'Female',
            'guest_age' => 42,
            'guest_full_address' => 'Santa, Ilocos Sur',
            'guest_contact_number' => '09191234567',
            'id_type' => 'Passport',
            'id_number' => 'PP-2026-0002',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Official',
            'num_male_guests' => 1,
            'num_female_guests' => 1,
            'detailed_checkin_datetime' => Carbon::today()->subDays(2)->setTime(15, 0),
            'additional_requests' => ['extra_pillow', 'extra_bed'],
            'payment_mode' => 'Card',
            'payment_amount' => 6000.00,
            'payment_or_number' => 'OR-2026-0002',
            'or_date' => Carbon::today()->subDays(2)->toDateString(),
            'remarks' => 'Guest arrived with spouse. Extra pillows and bed provided.',
        ]);
        $rooms[12]->update(['status' => 'occupied']);

        ReservationCharge::create([
            'reservation_id' => $res2->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Suite – 5 nights × ₱2,500.00',
            'qty' => 5,
            'unit_price' => 2500.00,
            'amount' => 12500.00,
            'created_by' => $admin->id,
        ]);
        ReservationCharge::create([
            'reservation_id' => $res2->id,
            'charge_type' => 'addon',
            'scope_type' => 'reservation',
            'description' => 'Extra Bed',
            'qty' => 1,
            'unit_price' => 200.00,
            'amount' => 200.00,
            'meta' => ['service_code' => 'extra_bed'],
            'created_by' => $staff1->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res2->id,
            'amount' => 6000.00,
            'payment_mode' => 'Card',
            'reference_no' => 'OR-2026-0002',
            'or_date' => Carbon::today()->subDays(2)->toDateString(),
            'status' => 'posted',
            'received_by' => $staff1->id,
            'received_at' => Carbon::today()->subDays(2)->setTime(15, 0),
            'remarks' => 'Advance payment on check-in',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res2->id,
            'guest_id' => $guest2a->id,
            'id_type' => 'Passport',
            'id_number' => 'PP-2026-0002',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Official',
            'detailed_checkin_datetime' => Carbon::today()->subDays(2)->setTime(15, 0),
            'payment_mode' => 'Card',
            'payment_amount' => 6000.00,
            'payment_or_number' => 'OR-2026-0002',
            'or_date' => Carbon::today()->subDays(2)->toDateString(),
            'additional_requests' => ['extra_pillow', 'extra_bed'],
            'remarks' => 'Arrived with spouse Diego Silang. Extra bed & pillows provided.',
            'captured_by' => $staff1->id,
            'captured_at' => Carbon::today()->subDays(2)->setTime(15, 0),
        ]);

        // === 3. Checked-in (standard room – solo) ========================
        $res3 = Reservation::create([
            'reference_number' => '2026-0003',
            'guest_last_name' => 'Bonifacio',
            'guest_first_name' => 'Andres',
            'guest_middle_initial' => 'B.',
            'guest_email' => 'andres.b@email.com',
            'guest_phone' => '09181234567',
            'guest_address' => 'Tondo, Manila',
            'guest_gender' => 'Male',
            'guest_age' => 30,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'preferred_room_type_id' => $standard->id,
            'check_in_date' => Carbon::today()->subDay(),
            'check_out_date' => Carbon::today()->addDays(2),
            'number_of_occupants' => 1,
            'purpose' => 'personal',
            'status' => 'checked_in',
            'reviewed_by' => $staff2->id,
            'reviewed_at' => Carbon::today()->subDays(3),
            'addons_total' => 50.00,
            'payments_total' => 2450.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
        ]);

        $guest3 = Guest::create([
            'reservation_id' => $res3->id,
            'full_name' => 'Andres B. Bonifacio',
            'first_name' => 'Andres',
            'last_name' => 'Bonifacio',
            'middle_initial' => 'B.',
            'relationship_to_primary' => 'self',
            'age' => 30,
            'gender' => 'Male',
            'contact_number' => '09181234567',
            'id_type' => "Driver's License",
            'id_number' => 'DL-2026-0003',
        ]);
        $res3->update(['billing_guest_id' => $guest3->id]);

        RoomAssignment::create([
            'reservation_id' => $res3->id,
            'guest_id' => $guest3->id,
            'room_id' => $rooms[0]->id, // Room 101
            'assigned_by' => $staff2->id,
            'assigned_at' => Carbon::today()->subDays(2),
            'checked_in_at' => Carbon::today()->subDay()->setTime(13, 30),
            'checked_in_by' => $staff2->id,
            'status' => 'checked_in',
            'guest_last_name' => 'Bonifacio',
            'guest_first_name' => 'Andres',
            'guest_middle_initial' => 'B.',
            'guest_gender' => 'Male',
            'guest_age' => 30,
            'guest_full_address' => 'Tondo, Manila',
            'guest_contact_number' => '09181234567',
            'id_type' => "Driver's License",
            'id_number' => 'DL-2026-0003',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Personal',
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'detailed_checkin_datetime' => Carbon::today()->subDay()->setTime(13, 30),
            'payment_mode' => 'Cash',
            'payment_amount' => 2450.00,
            'payment_or_number' => 'OR-2026-0003',
            'or_date' => Carbon::today()->subDay()->toDateString(),
        ]);
        $rooms[0]->update(['status' => 'occupied']);

        ReservationCharge::create([
            'reservation_id' => $res3->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Standard Room – 3 nights × ₱800.00',
            'qty' => 3,
            'unit_price' => 800.00,
            'amount' => 2400.00,
            'created_by' => $staff2->id,
        ]);
        ReservationCharge::create([
            'reservation_id' => $res3->id,
            'charge_type' => 'addon',
            'scope_type' => 'reservation',
            'description' => 'Iron Rental',
            'qty' => 1,
            'unit_price' => 50.00,
            'amount' => 50.00,
            'meta' => ['service_code' => 'iron_rental'],
            'created_by' => $staff2->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res3->id,
            'amount' => 2450.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0003',
            'or_date' => Carbon::today()->subDay()->toDateString(),
            'status' => 'posted',
            'received_by' => $staff2->id,
            'received_at' => Carbon::today()->subDay()->setTime(13, 30),
            'remarks' => 'Full payment on check-in',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res3->id,
            'guest_id' => $guest3->id,
            'id_type' => "Driver's License",
            'id_number' => 'DL-2026-0003',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Personal',
            'detailed_checkin_datetime' => Carbon::today()->subDay()->setTime(13, 30),
            'payment_mode' => 'Cash',
            'payment_amount' => 2450.00,
            'payment_or_number' => 'OR-2026-0003',
            'or_date' => Carbon::today()->subDay()->toDateString(),
            'additional_requests' => ['iron_rental'],
            'remarks' => 'Solo guest. Requested iron rental.',
            'captured_by' => $staff2->id,
            'captured_at' => Carbon::today()->subDay()->setTime(13, 30),
        ]);

        // === 4. Checked-in (family room – 3 occupants, with children) ====
        $res4 = Reservation::create([
            'reference_number' => '2026-0004',
            'guest_last_name' => 'Santos',
            'guest_first_name' => 'Lilia',
            'guest_middle_initial' => 'R.',
            'guest_email' => 'lilia.santos@email.com',
            'guest_phone' => '09351234567',
            'guest_address' => 'Davao City',
            'guest_gender' => 'Female',
            'guest_age' => 38,
            'num_male_guests' => 1,
            'num_female_guests' => 2,
            'preferred_room_type_id' => $family->id,
            'check_in_date' => Carbon::today()->subDay(),
            'check_out_date' => Carbon::today()->addDays(4),
            'number_of_occupants' => 3,
            'purpose' => 'event',
            'special_requests' => 'Attending graduation ceremony',
            'status' => 'checked_in',
            'reviewed_by' => $staff1->id,
            'reviewed_at' => Carbon::today()->subDays(3),
            'addons_total' => 0.00,
            'payments_total' => 5000.00,
            'balance_due' => 5000.00,
            'payment_status' => 'partially_paid',
        ]);

        $guest4a = Guest::create([
            'reservation_id' => $res4->id,
            'full_name' => 'Lilia R. Santos',
            'first_name' => 'Lilia',
            'last_name' => 'Santos',
            'middle_initial' => 'R.',
            'relationship_to_primary' => 'self',
            'age' => 38,
            'gender' => 'Female',
            'contact_number' => '09351234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260004',
        ]);
        $guest4b = Guest::create([
            'reservation_id' => $res4->id,
            'full_name' => 'Ricardo M. Santos',
            'first_name' => 'Ricardo',
            'last_name' => 'Santos',
            'middle_initial' => 'M.',
            'relationship_to_primary' => 'Spouse',
            'age' => 40,
            'gender' => 'Male',
        ]);
        $guest4c = Guest::create([
            'reservation_id' => $res4->id,
            'full_name' => 'Anna Santos',
            'first_name' => 'Anna',
            'last_name' => 'Santos',
            'relationship_to_primary' => 'Child',
            'age' => 12,
            'gender' => 'Female',
        ]);
        $res4->update(['billing_guest_id' => $guest4a->id]);

        RoomAssignment::create([
            'reservation_id' => $res4->id,
            'guest_id' => $guest4a->id,
            'room_id' => $rooms[14]->id, // Room 303
            'assigned_by' => $staff1->id,
            'assigned_at' => Carbon::today()->subDays(2),
            'checked_in_at' => Carbon::today()->subDay()->setTime(14, 0),
            'checked_in_by' => $staff1->id,
            'status' => 'checked_in',
            'guest_last_name' => 'Santos',
            'guest_first_name' => 'Lilia',
            'guest_middle_initial' => 'R.',
            'guest_gender' => 'Female',
            'guest_age' => 38,
            'guest_full_address' => 'Davao City',
            'guest_contact_number' => '09351234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260004',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Event',
            'num_male_guests' => 1,
            'num_female_guests' => 2,
            'detailed_checkin_datetime' => Carbon::today()->subDay()->setTime(14, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 5000.00,
            'payment_or_number' => 'OR-2026-0004',
            'or_date' => Carbon::today()->subDay()->toDateString(),
            'remarks' => 'Family of 3. Attending graduation ceremony.',
        ]);
        $rooms[14]->update(['status' => 'occupied']);

        ReservationCharge::create([
            'reservation_id' => $res4->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Family Room – 5 nights × ₱2,000.00',
            'qty' => 5,
            'unit_price' => 2000.00,
            'amount' => 10000.00,
            'created_by' => $staff1->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res4->id,
            'amount' => 5000.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0004',
            'or_date' => Carbon::today()->subDay()->toDateString(),
            'status' => 'posted',
            'received_by' => $staff1->id,
            'received_at' => Carbon::today()->subDay()->setTime(14, 0),
            'remarks' => 'Partial payment – balance on checkout',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res4->id,
            'guest_id' => $guest4a->id,
            'id_type' => 'National ID',
            'id_number' => 'NID-20260004',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Event',
            'detailed_checkin_datetime' => Carbon::today()->subDay()->setTime(14, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 5000.00,
            'payment_or_number' => 'OR-2026-0004',
            'or_date' => Carbon::today()->subDay()->toDateString(),
            'additional_requests' => [],
            'remarks' => 'Family of 3 for graduation.',
            'captured_by' => $staff1->id,
            'captured_at' => Carbon::today()->subDay()->setTime(14, 0),
        ]);

        // === 5. Checked-out (2nd completed – dormitory student group) ====
        $res5 = Reservation::create([
            'reference_number' => '2026-0005',
            'guest_last_name' => 'Jacinto',
            'guest_first_name' => 'Emilio',
            'guest_middle_initial' => 'A.',
            'guest_email' => 'emilio.j@email.com',
            'guest_phone' => '09321234567',
            'guest_address' => 'Trozo, Manila',
            'guest_gender' => 'Male',
            'guest_age' => 22,
            'num_male_guests' => 3,
            'num_female_guests' => 1,
            'preferred_room_type_id' => $dormitory->id,
            'check_in_date' => Carbon::today()->subDays(5),
            'check_out_date' => Carbon::today()->subDays(3),
            'number_of_occupants' => 4,
            'purpose' => 'academic',
            'special_requests' => 'Student research group from UP Manila',
            'status' => 'checked_out',
            'reviewed_by' => $staff2->id,
            'reviewed_at' => Carbon::today()->subDays(8),
            'addons_total' => 0.00,
            'payments_total' => 2800.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
        ]);

        $guest5a = Guest::create([
            'reservation_id' => $res5->id,
            'full_name' => 'Emilio A. Jacinto',
            'first_name' => 'Emilio',
            'last_name' => 'Jacinto',
            'middle_initial' => 'A.',
            'relationship_to_primary' => 'self',
            'age' => 22,
            'gender' => 'Male',
            'contact_number' => '09321234567',
            'id_type' => 'Student ID',
            'id_number' => 'UP-2024-12345',
        ]);
        Guest::create([
            'reservation_id' => $res5->id,
            'full_name' => 'Pedro Paterno',
            'first_name' => 'Pedro',
            'last_name' => 'Paterno',
            'relationship_to_primary' => 'Classmate',
            'age' => 23,
            'gender' => 'Male',
        ]);
        Guest::create([
            'reservation_id' => $res5->id,
            'full_name' => 'Ramon Magsaysay',
            'first_name' => 'Ramon',
            'last_name' => 'Magsaysay',
            'relationship_to_primary' => 'Classmate',
            'age' => 21,
            'gender' => 'Male',
        ]);
        Guest::create([
            'reservation_id' => $res5->id,
            'full_name' => 'Rosa Sevilla',
            'first_name' => 'Rosa',
            'last_name' => 'Sevilla',
            'relationship_to_primary' => 'Classmate',
            'age' => 22,
            'gender' => 'Female',
        ]);
        $res5->update(['billing_guest_id' => $guest5a->id]);

        RoomAssignment::create([
            'reservation_id' => $res5->id,
            'guest_id' => $guest5a->id,
            'room_id' => $rooms[5]->id, // Room 106
            'assigned_by' => $staff2->id,
            'assigned_at' => Carbon::today()->subDays(6),
            'checked_in_at' => Carbon::today()->subDays(5)->setTime(10, 0),
            'checked_in_by' => $staff2->id,
            'checked_out_at' => Carbon::today()->subDays(3)->setTime(9, 0),
            'checked_out_by' => $staff3->id,
            'status' => 'checked_out',
            'guest_last_name' => 'Jacinto',
            'guest_first_name' => 'Emilio',
            'guest_middle_initial' => 'A.',
            'guest_gender' => 'Male',
            'guest_age' => 22,
            'guest_full_address' => 'Trozo, Manila',
            'guest_contact_number' => '09321234567',
            'id_type' => 'Student ID',
            'id_number' => 'UP-2024-12345',
            'is_student' => true,
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'num_male_guests' => 3,
            'num_female_guests' => 1,
            'detailed_checkin_datetime' => Carbon::today()->subDays(5)->setTime(10, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(3)->setTime(9, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 2800.00,
            'payment_or_number' => 'OR-2026-0005',
            'or_date' => Carbon::today()->subDays(5)->toDateString(),
            'remarks' => 'Student group – 4 occupants in dormitory.',
        ]);

        ReservationCharge::create([
            'reservation_id' => $res5->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Dormitory – 4 persons × 2 nights × ₱350.00',
            'qty' => 8,
            'unit_price' => 350.00,
            'amount' => 2800.00,
            'created_by' => $staff2->id,
        ]);
        ReservationCharge::create([
            'reservation_id' => $res5->id,
            'charge_type' => 'discount',
            'scope_type' => 'reservation',
            'description' => 'Student Discount (10%)',
            'qty' => 1,
            'unit_price' => -280.00,
            'amount' => -280.00,
            'meta' => ['discount_types' => ['Student (10%)'], 'discount_percent' => 10],
            'created_by' => $staff2->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res5->id,
            'amount' => 2800.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0005',
            'or_date' => Carbon::today()->subDays(5)->toDateString(),
            'status' => 'posted',
            'received_by' => $staff2->id,
            'received_at' => Carbon::today()->subDays(5)->setTime(10, 0),
            'remarks' => 'Full payment with student discount applied',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res5->id,
            'guest_id' => $guest5a->id,
            'id_type' => 'Student ID',
            'id_number' => 'UP-2024-12345',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'detailed_checkin_datetime' => Carbon::today()->subDays(5)->setTime(10, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(3)->setTime(9, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 2800.00,
            'payment_or_number' => 'OR-2026-0005',
            'or_date' => Carbon::today()->subDays(5)->toDateString(),
            'additional_requests' => [],
            'remarks' => 'Student group of 4 – completed stay.',
            'captured_by' => $staff2->id,
            'captured_at' => Carbon::today()->subDays(5)->setTime(10, 0),
        ]);

        // === 6. Approved – arriving today (solo, deluxe) =================
        $res6 = Reservation::create([
            'reference_number' => '2026-0006',
            'guest_last_name' => 'Mabini',
            'guest_first_name' => 'Apolinario',
            'guest_middle_initial' => 'M.',
            'guest_email' => 'apolinario.m@email.com',
            'guest_phone' => '09201234567',
            'guest_address' => 'Tanauan, Batangas',
            'guest_gender' => 'Male',
            'guest_age' => 52,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
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
        Guest::create([
            'reservation_id' => $res6->id,
            'full_name' => 'Apolinario M. Mabini',
            'first_name' => 'Apolinario',
            'last_name' => 'Mabini',
            'middle_initial' => 'M.',
            'relationship_to_primary' => 'self',
            'age' => 52,
            'gender' => 'Male',
            'contact_number' => '09201234567',
        ]);

        // === 7. Approved – future arrival (family, 4 occupants) ==========
        $res7 = Reservation::create([
            'reference_number' => '2026-0007',
            'guest_last_name' => 'Magbanua',
            'guest_first_name' => 'Teresa',
            'guest_middle_initial' => 'F.',
            'guest_email' => 'teresa.m@email.com',
            'guest_phone' => '09231234567',
            'guest_address' => 'Iloilo City',
            'guest_gender' => 'Female',
            'guest_age' => 45,
            'num_male_guests' => 2,
            'num_female_guests' => 2,
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
        Guest::create([
            'reservation_id' => $res7->id,
            'full_name' => 'Teresa F. Magbanua',
            'first_name' => 'Teresa',
            'last_name' => 'Magbanua',
            'middle_initial' => 'F.',
            'relationship_to_primary' => 'self',
            'age' => 45,
            'gender' => 'Female',
            'contact_number' => '09231234567',
        ]);
        Guest::create([
            'reservation_id' => $res7->id,
            'full_name' => 'Carlos Magbanua',
            'first_name' => 'Carlos',
            'last_name' => 'Magbanua',
            'relationship_to_primary' => 'Spouse',
            'age' => 47,
            'gender' => 'Male',
        ]);
        Guest::create([
            'reservation_id' => $res7->id,
            'full_name' => 'Marco Magbanua',
            'first_name' => 'Marco',
            'last_name' => 'Magbanua',
            'relationship_to_primary' => 'Child',
            'age' => 18,
            'gender' => 'Male',
        ]);
        Guest::create([
            'reservation_id' => $res7->id,
            'full_name' => 'Isabel Magbanua',
            'first_name' => 'Isabel',
            'last_name' => 'Magbanua',
            'relationship_to_primary' => 'Child',
            'age' => 15,
            'gender' => 'Female',
        ]);

        // === 8. Pending review (solo, standard) ==========================
        Reservation::create([
            'reference_number' => '2026-0008',
            'guest_last_name' => 'Aquino',
            'guest_first_name' => 'Melchora',
            'guest_middle_initial' => 'D.',
            'guest_email' => 'melchora.a@email.com',
            'guest_phone' => '09211234567',
            'guest_address' => 'Caloocan City',
            'guest_gender' => 'Female',
            'guest_age' => 29,
            'num_male_guests' => 0,
            'num_female_guests' => 1,
            'preferred_room_type_id' => $standard->id,
            'check_in_date' => Carbon::today()->addDays(7),
            'check_out_date' => Carbon::today()->addDays(10),
            'number_of_occupants' => 1,
            'purpose' => 'personal',
            'status' => 'pending',
        ]);

        // === 9. Pending review (suite, 2 occupants) ======================
        Reservation::create([
            'reference_number' => '2026-0009',
            'guest_last_name' => 'Aguinaldo',
            'guest_first_name' => 'Emilio',
            'guest_middle_initial' => 'F.',
            'guest_email' => 'emilio.a@email.com',
            'guest_phone' => '09221234567',
            'guest_address' => 'Kawit, Cavite',
            'guest_gender' => 'Male',
            'guest_age' => 60,
            'num_male_guests' => 1,
            'num_female_guests' => 1,
            'preferred_room_type_id' => $suite->id,
            'check_in_date' => Carbon::today()->addDays(10),
            'check_out_date' => Carbon::today()->addDays(12),
            'number_of_occupants' => 2,
            'purpose' => 'official',
            'special_requests' => 'Need projector for presentation',
            'status' => 'pending',
        ]);

        // === 10. Pending review (dormitory, 6-person student group) ======
        Reservation::create([
            'reference_number' => '2026-0010',
            'guest_last_name' => 'Luna',
            'guest_first_name' => 'Antonio',
            'guest_middle_initial' => 'N.',
            'guest_email' => 'antonio.l@email.com',
            'guest_phone' => '09281234567',
            'guest_address' => 'Binondo, Manila',
            'guest_gender' => 'Male',
            'guest_age' => 24,
            'num_male_guests' => 4,
            'num_female_guests' => 2,
            'preferred_room_type_id' => $dormitory->id,
            'check_in_date' => Carbon::today()->addDays(3),
            'check_out_date' => Carbon::today()->addDays(5),
            'number_of_occupants' => 6,
            'purpose' => 'academic',
            'special_requests' => 'Student research group from Mindanao State University',
            'status' => 'pending',
        ]);

        // === 11. Declined ================================================
        Reservation::create([
            'reference_number' => '2026-0011',
            'guest_last_name' => 'Del Pilar',
            'guest_first_name' => 'Gregorio',
            'guest_middle_initial' => 'H.',
            'guest_email' => 'gregorio.dp@email.com',
            'guest_phone' => '09241234567',
            'guest_address' => 'Bulacan',
            'guest_gender' => 'Male',
            'guest_age' => 48,
            'num_male_guests' => 2,
            'num_female_guests' => 1,
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

        // === 12. Cancelled ===============================================
        Reservation::create([
            'reference_number' => '2026-0012',
            'guest_last_name' => 'Tecson',
            'guest_first_name' => 'Trinidad',
            'guest_middle_initial' => 'P.',
            'guest_email' => 'trinidad.t@email.com',
            'guest_phone' => '09261234567',
            'guest_address' => 'San Miguel, Bulacan',
            'guest_gender' => 'Female',
            'guest_age' => 33,
            'num_male_guests' => 0,
            'num_female_guests' => 1,
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

        // === 13. Checked-out – senior citizen with PWD discount ==========
        $res13 = Reservation::create([
            'reference_number' => '2026-0013',
            'guest_last_name' => 'Quezon',
            'guest_first_name' => 'Manuel',
            'guest_middle_initial' => 'L.',
            'guest_email' => 'manuel.q@email.com',
            'guest_phone' => '09301234567',
            'guest_address' => 'Baler, Aurora',
            'guest_gender' => 'Male',
            'guest_age' => 68,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'preferred_room_type_id' => $standard->id,
            'check_in_date' => Carbon::today()->subDays(10),
            'check_out_date' => Carbon::today()->subDays(8),
            'number_of_occupants' => 1,
            'purpose' => 'personal',
            'status' => 'checked_out',
            'reviewed_by' => $staff3->id,
            'reviewed_at' => Carbon::today()->subDays(12),
            'addons_total' => 0.00,
            'payments_total' => 1280.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
        ]);

        $guest13 = Guest::create([
            'reservation_id' => $res13->id,
            'full_name' => 'Manuel L. Quezon',
            'first_name' => 'Manuel',
            'last_name' => 'Quezon',
            'middle_initial' => 'L.',
            'relationship_to_primary' => 'self',
            'age' => 68,
            'gender' => 'Male',
            'contact_number' => '09301234567',
            'id_type' => 'Senior Citizen ID',
            'id_number' => 'SC-2026-0013',
        ]);
        $res13->update(['billing_guest_id' => $guest13->id]);

        RoomAssignment::create([
            'reservation_id' => $res13->id,
            'guest_id' => $guest13->id,
            'room_id' => $rooms[3]->id, // Room 104
            'assigned_by' => $staff3->id,
            'assigned_at' => Carbon::today()->subDays(11),
            'checked_in_at' => Carbon::today()->subDays(10)->setTime(14, 0),
            'checked_in_by' => $staff3->id,
            'checked_out_at' => Carbon::today()->subDays(8)->setTime(11, 0),
            'checked_out_by' => $staff1->id,
            'status' => 'checked_out',
            'guest_last_name' => 'Quezon',
            'guest_first_name' => 'Manuel',
            'guest_middle_initial' => 'L.',
            'guest_gender' => 'Male',
            'guest_age' => 68,
            'guest_full_address' => 'Baler, Aurora',
            'guest_contact_number' => '09301234567',
            'id_type' => 'Senior Citizen ID',
            'id_number' => 'SC-2026-0013',
            'is_senior_citizen' => true,
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Personal',
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'detailed_checkin_datetime' => Carbon::today()->subDays(10)->setTime(14, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(8)->setTime(11, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 1280.00,
            'payment_or_number' => 'OR-2026-0013',
            'or_date' => Carbon::today()->subDays(10)->toDateString(),
            'remarks' => 'Senior citizen – 20% discount applied.',
        ]);

        ReservationCharge::create([
            'reservation_id' => $res13->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Standard Room – 2 nights × ₱800.00',
            'qty' => 2,
            'unit_price' => 800.00,
            'amount' => 1600.00,
            'created_by' => $staff3->id,
        ]);
        ReservationCharge::create([
            'reservation_id' => $res13->id,
            'charge_type' => 'discount',
            'scope_type' => 'reservation',
            'description' => 'Senior Citizen Discount (20%)',
            'qty' => 1,
            'unit_price' => -320.00,
            'amount' => -320.00,
            'meta' => ['discount_types' => ['Senior Citizen (20%)'], 'discount_percent' => 20],
            'created_by' => $staff3->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res13->id,
            'amount' => 1280.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0013',
            'or_date' => Carbon::today()->subDays(10)->toDateString(),
            'status' => 'posted',
            'received_by' => $staff3->id,
            'received_at' => Carbon::today()->subDays(10)->setTime(14, 0),
            'remarks' => 'Full payment with senior discount',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res13->id,
            'guest_id' => $guest13->id,
            'id_type' => 'Senior Citizen ID',
            'id_number' => 'SC-2026-0013',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Personal',
            'detailed_checkin_datetime' => Carbon::today()->subDays(10)->setTime(14, 0),
            'detailed_checkout_datetime' => Carbon::today()->subDays(8)->setTime(11, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 1280.00,
            'payment_or_number' => 'OR-2026-0013',
            'or_date' => Carbon::today()->subDays(10)->toDateString(),
            'additional_requests' => [],
            'remarks' => 'Senior citizen guest – smooth stay.',
            'captured_by' => $staff3->id,
            'captured_at' => Carbon::today()->subDays(10)->setTime(14, 0),
        ]);

        // === 14. Pending payment =========================================
        $res14 = Reservation::create([
            'reference_number' => '2026-0014',
            'guest_last_name' => 'Palma',
            'guest_first_name' => 'Jose',
            'guest_middle_initial' => 'A.',
            'guest_email' => 'jose.palma@email.com',
            'guest_phone' => '09331234567',
            'guest_address' => 'Tondo, Manila',
            'guest_gender' => 'Male',
            'guest_age' => 41,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today()->addDays(2),
            'check_out_date' => Carbon::today()->addDays(4),
            'number_of_occupants' => 1,
            'purpose' => 'official',
            'status' => 'pending_payment',
            'admin_notes' => 'Approved – awaiting advance payment.',
            'reviewed_by' => $admin->id,
            'reviewed_at' => Carbon::today()->subDay(),
        ]);
        Guest::create([
            'reservation_id' => $res14->id,
            'full_name' => 'Jose A. Palma',
            'first_name' => 'Jose',
            'last_name' => 'Palma',
            'middle_initial' => 'A.',
            'relationship_to_primary' => 'self',
            'age' => 41,
            'gender' => 'Male',
            'contact_number' => '09331234567',
        ]);

        // === 15. Checked-in – with early check-in addon ==================
        $res15 = Reservation::create([
            'reference_number' => '2026-0015',
            'guest_last_name' => 'Calderon',
            'guest_first_name' => 'Felipe',
            'guest_middle_initial' => 'G.',
            'guest_email' => 'felipe.c@email.com',
            'guest_phone' => '09341234567',
            'guest_address' => 'Meycauayan, Bulacan',
            'guest_gender' => 'Male',
            'guest_age' => 55,
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'preferred_room_type_id' => $deluxe->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
            'number_of_occupants' => 1,
            'purpose' => 'academic',
            'special_requests' => 'Early check-in requested – arriving at 8 AM',
            'status' => 'checked_in',
            'reviewed_by' => $staff3->id,
            'reviewed_at' => Carbon::today()->subDays(2),
            'addons_total' => 300.00,
            'payments_total' => 3300.00,
            'balance_due' => 0.00,
            'payment_status' => 'paid',
        ]);

        $guest15 = Guest::create([
            'reservation_id' => $res15->id,
            'full_name' => 'Felipe G. Calderon',
            'first_name' => 'Felipe',
            'last_name' => 'Calderon',
            'middle_initial' => 'G.',
            'relationship_to_primary' => 'self',
            'age' => 55,
            'gender' => 'Male',
            'contact_number' => '09341234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260015',
        ]);
        $res15->update(['billing_guest_id' => $guest15->id]);

        RoomAssignment::create([
            'reservation_id' => $res15->id,
            'guest_id' => $guest15->id,
            'room_id' => $rooms[8]->id, // Room 202
            'assigned_by' => $staff3->id,
            'assigned_at' => Carbon::today()->subDay(),
            'checked_in_at' => Carbon::today()->setTime(8, 0),
            'checked_in_by' => $staff3->id,
            'status' => 'checked_in',
            'guest_last_name' => 'Calderon',
            'guest_first_name' => 'Felipe',
            'guest_middle_initial' => 'G.',
            'guest_gender' => 'Male',
            'guest_age' => 55,
            'guest_full_address' => 'Meycauayan, Bulacan',
            'guest_contact_number' => '09341234567',
            'id_type' => 'National ID',
            'id_number' => 'NID-20260015',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'num_male_guests' => 1,
            'num_female_guests' => 0,
            'detailed_checkin_datetime' => Carbon::today()->setTime(8, 0),
            'additional_requests' => ['early_checkin'],
            'payment_mode' => 'Cash',
            'payment_amount' => 3300.00,
            'payment_or_number' => 'OR-2026-0015',
            'or_date' => Carbon::today()->toDateString(),
            'remarks' => 'Early check-in at 8 AM. Additional fee charged.',
        ]);
        $rooms[8]->update(['status' => 'occupied']);

        ReservationCharge::create([
            'reservation_id' => $res15->id,
            'charge_type' => 'room_rate',
            'scope_type' => 'reservation',
            'description' => 'Deluxe Room – 2 nights × ₱1,500.00',
            'qty' => 2,
            'unit_price' => 1500.00,
            'amount' => 3000.00,
            'created_by' => $staff3->id,
        ]);
        ReservationCharge::create([
            'reservation_id' => $res15->id,
            'charge_type' => 'addon',
            'scope_type' => 'reservation',
            'description' => 'Early Check-in',
            'qty' => 1,
            'unit_price' => 300.00,
            'amount' => 300.00,
            'meta' => ['service_code' => 'early_checkin'],
            'created_by' => $staff3->id,
        ]);

        ReservationPayment::create([
            'reservation_id' => $res15->id,
            'amount' => 3300.00,
            'payment_mode' => 'Cash',
            'reference_no' => 'OR-2026-0015',
            'or_date' => Carbon::today()->toDateString(),
            'status' => 'posted',
            'received_by' => $staff3->id,
            'received_at' => Carbon::today()->setTime(8, 0),
            'remarks' => 'Full payment including early check-in fee',
        ]);

        CheckInSnapshot::create([
            'reservation_id' => $res15->id,
            'guest_id' => $guest15->id,
            'id_type' => 'National ID',
            'id_number' => 'NID-20260015',
            'nationality' => 'Filipino',
            'purpose_of_stay' => 'Academic',
            'detailed_checkin_datetime' => Carbon::today()->setTime(8, 0),
            'payment_mode' => 'Cash',
            'payment_amount' => 3300.00,
            'payment_or_number' => 'OR-2026-0015',
            'or_date' => Carbon::today()->toDateString(),
            'additional_requests' => ['early_checkin'],
            'remarks' => 'Early check-in – arrived at 8 AM.',
            'captured_by' => $staff3->id,
            'captured_at' => Carbon::today()->setTime(8, 0),
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // ─── Bulk Reservations (16 – 115) ────────────────────────────────
        // ═══════════════════════════════════════════════════════════════════

        $staffPool = [$staff1, $staff2, $staff3, $admin];
        $roomTypes = [$standard, $deluxe, $suite, $family, $dormitory];
        $roomTypeRates = [
            $standard->id => 800.00,
            $deluxe->id => 1500.00,
            $suite->id => 2500.00,
            $family->id => 2000.00,
            $dormitory->id => 350.00,
        ];
        $roomsByType = [
            $standard->id => [$rooms[0], $rooms[1], $rooms[3], $rooms[4]], // skip $rooms[2]=103 (maintenance)
            $deluxe->id => [$rooms[7], $rooms[8], $rooms[9], $rooms[10], $rooms[11]],
            $suite->id => [$rooms[12], $rooms[13]],
            $family->id => [$rooms[14], $rooms[15]],
            $dormitory->id => [$rooms[5]], // skip $rooms[6]=107 (inactive)
        ];

        $filipinoLastNames = [
            'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Mendoza', 'Garcia', 'Torres',
            'Villanueva', 'Santiago', 'Ramos', 'Castillo', 'Fernando', 'Dela Rosa',
            'Navarro', 'Soriano', 'Mercado', 'Pascual', 'Salvador', 'Hernandez',
            'Diaz', 'Lopez', 'Gutierrez', 'Aguilar', 'Bernardo', 'Flores',
            'Morales', 'Rivera', 'Perez', 'Jimenez', 'Manalo', 'Panganiban',
            'Tolentino', 'Dimaculangan', 'Espiritu', 'Lagman', 'Salazar',
            'Abad', 'Cariño', 'Velasco', 'Cortez', 'Enriquez', 'De Leon',
            'Macapagal', 'Magno', 'Canlas', 'Fajardo', 'Galang', 'Lim',
            'Tan', 'Sy', 'Chua', 'Ong', 'Go', 'Yu', 'Ang', 'Co',
        ];
        $filipinoFirstNamesMale = [
            'Jose', 'Juan', 'Pedro', 'Carlos', 'Mario', 'Roberto', 'Eduardo',
            'Ricardo', 'Fernando', 'Miguel', 'Rafael', 'Gabriel', 'Antonio',
            'Francisco', 'Luis', 'Ramon', 'Marco', 'Paolo', 'Sergio', 'Angelo',
            'Dennis', 'Mark', 'James', 'Bryan', 'Patrick', 'Kenneth', 'Jayson',
            'Rommel', 'Ariel', 'Rolando',
        ];
        $filipinoFirstNamesFemale = [
            'Maria', 'Ana', 'Rosa', 'Lucia', 'Elena', 'Carmen', 'Patricia',
            'Sophia', 'Isabela', 'Victoria', 'Beatriz', 'Corazon', 'Dolores',
            'Gloria', 'Luz', 'Rosario', 'Teresa', 'Angela', 'Cristina', 'Diana',
            'Grace', 'Joy', 'Karen', 'Michelle', 'Nicole', 'Vanessa', 'Jasmine',
            'Hannah', 'Celine', 'Bianca',
        ];
        $middleInitials = ['A.', 'B.', 'C.', 'D.', 'E.', 'F.', 'G.', 'H.', 'L.', 'M.', 'N.', 'P.', 'R.', 'S.', 'T.', 'V.'];
        $purposes = ['academic', 'official', 'personal', 'event', 'training', 'research', 'other'];
        $idTypes = ['National ID', "Driver's License", 'Passport', 'Student ID', 'Company ID', 'Government ID', 'Senior Citizen ID', 'PWD ID'];
        $addresses = [
            'Quezon City', 'Manila', 'Cebu City', 'Davao City', 'Makati City',
            'Pasig City', 'Taguig City', 'Baguio City', 'Iloilo City', 'Cagayan de Oro',
            'Zamboanga City', 'Bacolod City', 'General Santos City', 'Antipolo City',
            'Marikina City', 'Parañaque City', 'Las Piñas City', 'Caloocan City',
            'Muntinlupa City', 'San Juan City', 'Mandaluyong City', 'Pasay City',
            'Butuan City', 'Tacloban City', 'Legazpi City', 'Naga City',
            'San Fernando, Pampanga', 'Lipa City, Batangas', 'Cabanatuan City',
            'Dagupan City', 'Olongapo City', 'Angeles City',
        ];
        $nationalities = ['Filipino', 'Filipino', 'Filipino', 'Filipino', 'Filipino', 'Filipino', 'Filipino', 'Filipino', 'American', 'Korean', 'Japanese', 'Chinese', 'Australian'];
        $paymentModes = ['Cash', 'Card', 'Bank Transfer', 'GCash'];

        $statuses = ['pending', 'approved', 'pending_payment', 'declined', 'cancelled', 'checked_in', 'checked_out'];

        $seqNum = 15;

        for ($i = 16; $i <= 115; $i++) {
            $seqNum++;
            $refNum = '2026-'.str_pad($i, 4, '0', STR_PAD_LEFT);

            // Weighted status distribution for realistic data
            $statusRoll = rand(1, 100);
            if ($statusRoll <= 25) {
                $status = 'checked_out';
            } elseif ($statusRoll <= 40) {
                $status = 'checked_in';
            } elseif ($statusRoll <= 55) {
                $status = 'approved';
            } elseif ($statusRoll <= 75) {
                $status = 'pending';
            } elseif ($statusRoll <= 82) {
                $status = 'pending_payment';
            } elseif ($statusRoll <= 90) {
                $status = 'declined';
            } else {
                $status = 'cancelled';
            }

            $isMale = rand(0, 1) === 1;
            $firstName = $isMale
                ? $filipinoFirstNamesMale[array_rand($filipinoFirstNamesMale)]
                : $filipinoFirstNamesFemale[array_rand($filipinoFirstNamesFemale)];
            $lastName = $filipinoLastNames[array_rand($filipinoLastNames)];
            $middleInit = $middleInitials[array_rand($middleInitials)];
            $gender = $isMale ? 'Male' : 'Female';
            $age = rand(19, 72);
            $address = $addresses[array_rand($addresses)];
            $purpose = $purposes[array_rand($purposes)];
            $email = strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName)).$i.'@email.com';
            $phone = '09'.rand(10, 99).rand(1000000, 9999999);

            // Pick room type
            $roomType = $roomTypes[array_rand($roomTypes)];
            $rate = $roomTypeRates[$roomType->id];
            $isDorm = $roomType->id === $dormitory->id;

            // Occupancy
            if ($isDorm) {
                $numOccupants = rand(2, 6);
            } elseif ($roomType->id === $family->id) {
                $numOccupants = rand(2, 5);
            } elseif ($roomType->id === $suite->id) {
                $numOccupants = rand(1, 3);
            } else {
                $numOccupants = rand(1, 2);
            }
            $numMale = $isMale ? rand(1, $numOccupants) : rand(0, max(0, $numOccupants - 1));
            $numFemale = $numOccupants - $numMale;

            // Date logic – spread across past and future to create overlapping days
            if (in_array($status, ['checked_out'])) {
                $checkInOffset = rand(-60, -3);
                $nights = rand(1, 5);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
                if ($checkOut->gt(Carbon::today())) {
                    $checkOut = Carbon::today()->subDay();
                    $nights = max(1, $checkIn->diffInDays($checkOut));
                }
            } elseif (in_array($status, ['checked_in'])) {
                $checkInOffset = rand(-5, 0);
                $nights = rand(1, 7);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
                if ($checkOut->lte(Carbon::today())) {
                    $checkOut = Carbon::today()->addDays(rand(1, 3));
                    $nights = max(1, $checkIn->diffInDays($checkOut));
                }
            } elseif (in_array($status, ['approved', 'pending_payment'])) {
                $checkInOffset = rand(0, 30);
                $nights = rand(1, 5);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            } elseif ($status === 'pending') {
                $checkInOffset = rand(1, 45);
                $nights = rand(1, 5);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            } else { // declined, cancelled
                $checkInOffset = rand(-10, 20);
                $nights = rand(1, 4);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            }

            // Discount logic
            $isStudent = ($age <= 25 && rand(1, 3) === 1);
            $isSenior = ($age >= 60 && rand(1, 2) === 1);
            $isPwd = (rand(1, 12) === 1);
            $discountTypes = [];
            $discountPercent = 0;
            if ($isStudent) {
                $discountTypes[] = 'Student (10%)';
                $discountPercent += 10;
            }
            if ($isSenior) {
                $discountTypes[] = 'Senior Citizen (20%)';
                $discountPercent += 20;
            }
            if ($isPwd) {
                $discountTypes[] = 'PWD (20%)';
                $discountPercent += 20;
            }
            $discountPercent = min($discountPercent, 100);

            // Compute charges
            if ($isDorm) {
                $roomCharge = $rate * $numOccupants * $nights;
                $chargeDesc = "Dormitory – {$numOccupants} persons × {$nights} night(s) × ₱".number_format($rate, 2);
                $chargeQty = $numOccupants * $nights;
            } else {
                $roomCharge = $rate * $nights;
                $chargeDesc = "{$roomType->name} – {$nights} night(s) × ₱".number_format($rate, 2);
                $chargeQty = $nights;
            }
            $discountAmount = ($roomCharge * $discountPercent) / 100;
            $totalDue = $roomCharge - $discountAmount;

            // Payment amounts based on status
            $paymentAmount = 0;
            $balanceDue = 0;
            $paymentStatus = 'unpaid';
            if (in_array($status, ['checked_out'])) {
                $paymentAmount = $totalDue;
                $balanceDue = 0;
                $paymentStatus = 'paid';
            } elseif ($status === 'checked_in') {
                if (rand(1, 3) === 1) {
                    $paymentAmount = round($totalDue * rand(40, 70) / 100, 2);
                    $balanceDue = round($totalDue - $paymentAmount, 2);
                    $paymentStatus = 'partially_paid';
                } else {
                    $paymentAmount = $totalDue;
                    $balanceDue = 0;
                    $paymentStatus = 'paid';
                }
            }

            $reviewedBy = null;
            $reviewedAt = null;
            if (in_array($status, ['approved', 'checked_in', 'checked_out', 'declined', 'cancelled', 'pending_payment'])) {
                $reviewer = $staffPool[array_rand($staffPool)];
                $reviewedBy = $reviewer->id;
                $reviewedAt = $checkIn->copy()->subDays(rand(1, 5));
            }

            $specialRequests = null;
            if (rand(1, 4) === 1) {
                $requests = [
                    'Need extra pillows', 'Late checkout requested', 'Quiet room preferred',
                    'Ground floor preferred', 'Near elevator please', 'Extra towels needed',
                    'Attending university conference', 'Student group from CMU',
                    'Require accessible room', 'Celebrating anniversary',
                    'Will arrive late evening', 'Need parking space',
                    'Vegetarian meals requested', 'Connecting rooms if possible',
                ];
                $specialRequests = $requests[array_rand($requests)];
            }

            $res = Reservation::create([
                'reference_number' => $refNum,
                'guest_last_name' => $lastName,
                'guest_first_name' => $firstName,
                'guest_middle_initial' => $middleInit,
                'guest_email' => $email,
                'guest_phone' => $phone,
                'guest_address' => $address,
                'guest_gender' => $gender,
                'guest_age' => $age,
                'num_male_guests' => $numMale,
                'num_female_guests' => $numFemale,
                'preferred_room_type_id' => $roomType->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'number_of_occupants' => $numOccupants,
                'purpose' => $purpose,
                'special_requests' => $specialRequests,
                'status' => $status,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'addons_total' => 0,
                'payments_total' => $paymentAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
            ]);

            // Create primary guest
            $guest = Guest::create([
                'reservation_id' => $res->id,
                'full_name' => "{$firstName} {$middleInit} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_initial' => $middleInit,
                'relationship_to_primary' => 'self',
                'age' => $age,
                'gender' => $gender,
                'contact_number' => $phone,
                'id_type' => $idTypes[array_rand($idTypes)],
                'id_number' => 'ID-2026-'.str_pad($i, 4, '0', STR_PAD_LEFT),
            ]);

            // Add companion guests for multi-occupancy
            for ($g = 2; $g <= $numOccupants; $g++) {
                $compMale = rand(0, 1) === 1;
                $compFirst = $compMale
                    ? $filipinoFirstNamesMale[array_rand($filipinoFirstNamesMale)]
                    : $filipinoFirstNamesFemale[array_rand($filipinoFirstNamesFemale)];
                $relationships = ['Spouse', 'Sibling', 'Child', 'Colleague', 'Friend', 'Relative'];
                Guest::create([
                    'reservation_id' => $res->id,
                    'full_name' => "{$compFirst} {$lastName}",
                    'first_name' => $compFirst,
                    'last_name' => $lastName,
                    'relationship_to_primary' => $relationships[array_rand($relationships)],
                    'age' => rand(12, 65),
                    'gender' => $compMale ? 'Male' : 'Female',
                ]);
            }

            $res->update(['billing_guest_id' => $guest->id]);

            // For checked_in / checked_out – create full records
            if (in_array($status, ['checked_in', 'checked_out'])) {
                $availableRooms = $roomsByType[$roomType->id] ?? [];
                $room = $availableRooms[array_rand($availableRooms)];
                $assigner = $staffPool[array_rand($staffPool)];
                $nationality = $nationalities[array_rand($nationalities)];
                $payMode = $paymentModes[array_rand($paymentModes)];
                $orNumber = 'OR-2026-'.str_pad($i, 4, '0', STR_PAD_LEFT);
                $orDate = $checkIn->toDateString();

                $checkedInAt = $checkIn->copy()->setTime(rand(8, 17), rand(0, 59));
                $checkedOutAt = null;
                $assignmentStatus = 'checked_in';

                if ($status === 'checked_out') {
                    $checkedOutAt = $checkOut->copy()->setTime(rand(8, 12), rand(0, 59));
                    $assignmentStatus = 'checked_out';
                }

                RoomAssignment::create([
                    'reservation_id' => $res->id,
                    'guest_id' => $guest->id,
                    'room_id' => $room->id,
                    'assigned_by' => $assigner->id,
                    'assigned_at' => $checkIn->copy()->subDay(),
                    'checked_in_at' => $checkedInAt,
                    'checked_in_by' => $assigner->id,
                    'checked_out_at' => $checkedOutAt,
                    'checked_out_by' => $checkedOutAt ? $staffPool[array_rand($staffPool)]->id : null,
                    'status' => $assignmentStatus,
                    'guest_last_name' => $lastName,
                    'guest_first_name' => $firstName,
                    'guest_middle_initial' => $middleInit,
                    'guest_gender' => $gender,
                    'guest_age' => $age,
                    'guest_full_address' => $address,
                    'guest_contact_number' => $phone,
                    'id_type' => $guest->id_type,
                    'id_number' => $guest->id_number,
                    'is_student' => $isStudent,
                    'is_senior_citizen' => $isSenior,
                    'is_pwd' => $isPwd,
                    'nationality' => $nationality,
                    'purpose_of_stay' => ucfirst($purpose),
                    'num_male_guests' => $numMale,
                    'num_female_guests' => $numFemale,
                    'detailed_checkin_datetime' => $checkedInAt,
                    'detailed_checkout_datetime' => $checkedOutAt,
                    'payment_mode' => $payMode,
                    'payment_amount' => $paymentAmount,
                    'payment_or_number' => $orNumber,
                    'or_date' => $orDate,
                    'remarks' => "Bulk seeded reservation #{$i}.",
                ]);

                // Room rate charge
                ReservationCharge::create([
                    'reservation_id' => $res->id,
                    'charge_type' => 'room_rate',
                    'scope_type' => 'reservation',
                    'scope_id' => $res->id,
                    'description' => $chargeDesc,
                    'qty' => $chargeQty,
                    'unit_price' => $rate,
                    'amount' => $roomCharge,
                    'created_by' => $assigner->id,
                ]);

                // Discount charge
                if ($discountAmount > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $res->id,
                        'charge_type' => 'discount',
                        'scope_type' => 'reservation',
                        'scope_id' => $res->id,
                        'description' => 'Discount: '.implode(' + ', $discountTypes),
                        'qty' => 1,
                        'unit_price' => -$discountAmount,
                        'amount' => -$discountAmount,
                        'meta' => [
                            'discount_types' => $discountTypes,
                            'discount_percent' => $discountPercent,
                            'subtotal_before_discount' => $roomCharge,
                        ],
                        'created_by' => $assigner->id,
                    ]);
                }

                // Payment record
                if ($paymentAmount > 0) {
                    ReservationPayment::create([
                        'reservation_id' => $res->id,
                        'amount' => $paymentAmount,
                        'payment_mode' => $payMode,
                        'reference_no' => $orNumber,
                        'or_date' => $orDate,
                        'status' => 'posted',
                        'received_by' => $assigner->id,
                        'received_at' => $checkedInAt,
                        'remarks' => $paymentStatus === 'paid' ? 'Full payment' : 'Partial payment',
                    ]);
                }

                // Check-in snapshot
                CheckInSnapshot::create([
                    'reservation_id' => $res->id,
                    'guest_id' => $guest->id,
                    'id_type' => $guest->id_type,
                    'id_number' => $guest->id_number,
                    'nationality' => $nationality,
                    'purpose_of_stay' => ucfirst($purpose),
                    'detailed_checkin_datetime' => $checkedInAt,
                    'detailed_checkout_datetime' => $checkedOutAt,
                    'payment_mode' => $payMode,
                    'payment_amount' => $paymentAmount,
                    'payment_or_number' => $orNumber,
                    'or_date' => $orDate,
                    'additional_requests' => [],
                    'remarks' => "Seeded reservation #{$i}",
                    'captured_by' => $assigner->id,
                    'captured_at' => $checkedInAt,
                ]);
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // ─── Bulk Reservations Batch 2 (116 – 1000) ─────────────────────
        //     Includes multi-room reservations by a single guest
        // ═══════════════════════════════════════════════════════════════════

        // All available room objects indexed by type for multi-room picks
        $allRoomsByType = [
            $standard->id => [$rooms[0], $rooms[1], $rooms[3], $rooms[4]], // skip 102 (maintenance)
            $deluxe->id => [$rooms[7], $rooms[8], $rooms[9], $rooms[10], $rooms[11]],
            $suite->id => [$rooms[12], $rooms[13]],
            $family->id => [$rooms[14], $rooms[15]],
            $dormitory->id => [$rooms[5]], // skip 107 (inactive)
        ];

        $specialRequestPool = [
            'Need extra pillows', 'Late checkout requested', 'Quiet room preferred',
            'Require accessible room', 'Will arrive late evening', 'Need parking space',
            'Attending university seminar', 'Faculty training workshop',
            'Visiting research fellow', 'Government delegation visit',
            'Student orientation group', 'Alumni reunion event',
            'Medical mission accommodations', 'Extension service team',
            'Board exam reviewees', 'Internship placement group',
        ];

        for ($i = 116; $i <= 1000; $i++) {
            $seqNum++;
            $refNum = '2026-'.str_pad($i, 4, '0', STR_PAD_LEFT);

            // Weighted status distribution
            $statusRoll = rand(1, 100);
            if ($statusRoll <= 25) {
                $status = 'checked_out';
            } elseif ($statusRoll <= 42) {
                $status = 'checked_in';
            } elseif ($statusRoll <= 55) {
                $status = 'approved';
            } elseif ($statusRoll <= 75) {
                $status = 'pending';
            } elseif ($statusRoll <= 82) {
                $status = 'pending_payment';
            } elseif ($statusRoll <= 91) {
                $status = 'declined';
            } else {
                $status = 'cancelled';
            }

            $isMale = rand(0, 1) === 1;
            $firstName = $isMale
                ? $filipinoFirstNamesMale[array_rand($filipinoFirstNamesMale)]
                : $filipinoFirstNamesFemale[array_rand($filipinoFirstNamesFemale)];
            $lastName = $filipinoLastNames[array_rand($filipinoLastNames)];
            $middleInit = $middleInitials[array_rand($middleInitials)];
            $gender = $isMale ? 'Male' : 'Female';
            $age = rand(18, 75);
            $address = $addresses[array_rand($addresses)];
            $purpose = $purposes[array_rand($purposes)];
            $email = strtolower($firstName).'.'.strtolower(str_replace(' ', '', $lastName)).$i.'@email.com';
            $phone = '09'.rand(10, 99).rand(1000000, 9999999);

            // Decide if this is a multi-room reservation (~20% chance for checked_in/checked_out)
            $isMultiRoom = in_array($status, ['checked_in', 'checked_out']) && rand(1, 5) === 1;

            // Pick room type(s)
            $roomType = $roomTypes[array_rand($roomTypes)];
            $rate = $roomTypeRates[$roomType->id];
            $isDorm = $roomType->id === $dormitory->id;

            // For multi-room: pick a 2nd room type (can be same or different)
            $roomType2 = null;
            $rate2 = 0;
            $isDorm2 = false;
            if ($isMultiRoom) {
                // Pick from non-dorm types for 2nd room to keep it realistic
                $nonDormTypes = [$standard, $deluxe, $suite, $family];
                $roomType2 = $nonDormTypes[array_rand($nonDormTypes)];
                $rate2 = $roomTypeRates[$roomType2->id];
                $isDorm2 = false;
            }

            // Occupancy
            if ($isDorm) {
                $numOccupants = rand(2, 7);
            } elseif ($roomType->id === $family->id || $isMultiRoom) {
                $numOccupants = rand(3, 6);
            } elseif ($roomType->id === $suite->id) {
                $numOccupants = rand(1, 3);
            } else {
                $numOccupants = rand(1, 2);
            }
            $numMale = $isMale ? rand(1, $numOccupants) : rand(0, max(0, $numOccupants - 1));
            $numFemale = $numOccupants - $numMale;

            // Date logic
            if ($status === 'checked_out') {
                $checkInOffset = rand(-90, -2);
                $nights = rand(1, 7);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
                if ($checkOut->gt(Carbon::today())) {
                    $checkOut = Carbon::today()->subDay();
                    $nights = max(1, $checkIn->diffInDays($checkOut));
                }
            } elseif ($status === 'checked_in') {
                $checkInOffset = rand(-4, 0);
                $nights = rand(1, 8);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
                if ($checkOut->lte(Carbon::today())) {
                    $checkOut = Carbon::today()->addDays(rand(1, 4));
                    $nights = max(1, $checkIn->diffInDays($checkOut));
                }
            } elseif (in_array($status, ['approved', 'pending_payment'])) {
                $checkInOffset = rand(0, 40);
                $nights = rand(1, 5);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            } elseif ($status === 'pending') {
                $checkInOffset = rand(1, 60);
                $nights = rand(1, 7);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            } else {
                $checkInOffset = rand(-15, 25);
                $nights = rand(1, 4);
                $checkIn = Carbon::today()->addDays($checkInOffset);
                $checkOut = $checkIn->copy()->addDays($nights);
            }

            // Discount logic
            $isStudent = ($age <= 25 && rand(1, 3) === 1);
            $isSenior = ($age >= 60 && rand(1, 2) === 1);
            $isPwd = (rand(1, 10) === 1);
            $discountTypes = [];
            $discountPercent = 0;
            if ($isStudent) {
                $discountTypes[] = 'Student (10%)';
                $discountPercent += 10;
            }
            if ($isSenior) {
                $discountTypes[] = 'Senior Citizen (20%)';
                $discountPercent += 20;
            }
            if ($isPwd) {
                $discountTypes[] = 'PWD (20%)';
                $discountPercent += 20;
            }
            $discountPercent = min($discountPercent, 100);

            // Compute charges for room 1
            if ($isDorm) {
                $roomCharge = $rate * $numOccupants * $nights;
                $chargeDesc = "Dormitory – {$numOccupants} persons × {$nights} night(s) × ₱".number_format($rate, 2);
                $chargeQty = $numOccupants * $nights;
            } else {
                $roomCharge = $rate * $nights;
                $chargeDesc = "{$roomType->name} – {$nights} night(s) × ₱".number_format($rate, 2);
                $chargeQty = $nights;
            }

            // Compute charges for room 2 (if multi-room)
            $roomCharge2 = 0;
            $chargeDesc2 = '';
            $chargeQty2 = 0;
            if ($isMultiRoom && $roomType2) {
                $roomCharge2 = $rate2 * $nights;
                $chargeDesc2 = "{$roomType2->name} – {$nights} night(s) × ₱".number_format($rate2, 2);
                $chargeQty2 = $nights;
            }

            $totalRoomCharge = $roomCharge + $roomCharge2;
            $discountAmount = ($totalRoomCharge * $discountPercent) / 100;
            $totalDue = $totalRoomCharge - $discountAmount;

            // Addon chance
            $addonTotal = 0;
            $addonCode = null;
            $addonName = null;
            $addonPrice = 0;
            if (rand(1, 5) === 1 && in_array($status, ['checked_in', 'checked_out'])) {
                $addonOptions = [
                    ['code' => 'extra_bed', 'name' => 'Extra Bed', 'price' => 200.00],
                    ['code' => 'iron_rental', 'name' => 'Iron Rental', 'price' => 50.00],
                    ['code' => 'early_checkin', 'name' => 'Early Check-in', 'price' => 300.00],
                    ['code' => 'late_checkout', 'name' => 'Late Check-out', 'price' => 300.00],
                    ['code' => 'hair_dryer', 'name' => 'Hair Dryer', 'price' => 30.00],
                ];
                $picked = $addonOptions[array_rand($addonOptions)];
                $addonCode = $picked['code'];
                $addonName = $picked['name'];
                $addonPrice = $picked['price'];
                $addonTotal = $addonPrice;
                $totalDue += $addonTotal;
            }

            // Payment amounts
            $paymentAmount = 0;
            $balanceDue = 0;
            $paymentStatus = 'unpaid';
            if ($status === 'checked_out') {
                $paymentAmount = $totalDue;
                $balanceDue = 0;
                $paymentStatus = 'paid';
            } elseif ($status === 'checked_in') {
                if (rand(1, 4) === 1) {
                    $paymentAmount = round($totalDue * rand(30, 70) / 100, 2);
                    $balanceDue = round($totalDue - $paymentAmount, 2);
                    $paymentStatus = 'partially_paid';
                } else {
                    $paymentAmount = $totalDue;
                    $balanceDue = 0;
                    $paymentStatus = 'paid';
                }
            }

            $reviewedBy = null;
            $reviewedAt = null;
            if (in_array($status, ['approved', 'checked_in', 'checked_out', 'declined', 'cancelled', 'pending_payment'])) {
                $reviewer = $staffPool[array_rand($staffPool)];
                $reviewedBy = $reviewer->id;
                $reviewedAt = $checkIn->copy()->subDays(rand(1, 5));
            }

            $specialRequests = rand(1, 3) === 1 ? $specialRequestPool[array_rand($specialRequestPool)] : null;
            $adminNotes = null;
            if ($status === 'declined') {
                $declineReasons = [
                    'No rooms available for requested dates.',
                    'Requested room type fully booked.',
                    'Duplicate reservation detected.',
                    'Incomplete guest information provided.',
                ];
                $adminNotes = $declineReasons[array_rand($declineReasons)];
            } elseif ($status === 'cancelled') {
                $cancelReasons = [
                    'Guest cancelled due to change of plans.',
                    'Event postponed – guest no longer needs accommodation.',
                    'Guest found alternative lodging.',
                    'Travel plans cancelled.',
                ];
                $adminNotes = $cancelReasons[array_rand($cancelReasons)];
            } elseif ($isMultiRoom) {
                $adminNotes = 'Multi-room reservation – group booking.';
            }

            $res = Reservation::create([
                'reference_number' => $refNum,
                'guest_last_name' => $lastName,
                'guest_first_name' => $firstName,
                'guest_middle_initial' => $middleInit,
                'guest_email' => $email,
                'guest_phone' => $phone,
                'guest_address' => $address,
                'guest_gender' => $gender,
                'guest_age' => $age,
                'num_male_guests' => $numMale,
                'num_female_guests' => $numFemale,
                'preferred_room_type_id' => $roomType->id,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'number_of_occupants' => $numOccupants,
                'purpose' => $purpose,
                'special_requests' => $specialRequests,
                'status' => $status,
                'admin_notes' => $adminNotes,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => $reviewedAt,
                'addons_total' => $addonTotal,
                'payments_total' => $paymentAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
            ]);

            // Primary guest
            $idType = $idTypes[array_rand($idTypes)];
            $idNumber = 'ID-2026-'.str_pad($i, 4, '0', STR_PAD_LEFT);
            $guest = Guest::create([
                'reservation_id' => $res->id,
                'full_name' => "{$firstName} {$middleInit} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_initial' => $middleInit,
                'relationship_to_primary' => 'self',
                'age' => $age,
                'gender' => $gender,
                'contact_number' => $phone,
                'id_type' => $idType,
                'id_number' => $idNumber,
            ]);

            // Companion guests
            $companionGuests = [];
            for ($g = 2; $g <= $numOccupants; $g++) {
                $compMale = rand(0, 1) === 1;
                $compFirst = $compMale
                    ? $filipinoFirstNamesMale[array_rand($filipinoFirstNamesMale)]
                    : $filipinoFirstNamesFemale[array_rand($filipinoFirstNamesFemale)];
                $relationships = ['Spouse', 'Sibling', 'Child', 'Colleague', 'Friend', 'Relative'];
                $compGuest = Guest::create([
                    'reservation_id' => $res->id,
                    'full_name' => "{$compFirst} {$lastName}",
                    'first_name' => $compFirst,
                    'last_name' => $lastName,
                    'relationship_to_primary' => $relationships[array_rand($relationships)],
                    'age' => rand(10, 70),
                    'gender' => $compMale ? 'Male' : 'Female',
                ]);
                $companionGuests[] = $compGuest;
            }

            $res->update(['billing_guest_id' => $guest->id]);

            // Full records for checked_in / checked_out
            if (in_array($status, ['checked_in', 'checked_out'])) {
                $availableRooms1 = $allRoomsByType[$roomType->id] ?? [];
                $room = $availableRooms1[array_rand($availableRooms1)];
                $assigner = $staffPool[array_rand($staffPool)];
                $nationality = $nationalities[array_rand($nationalities)];
                $payMode = $paymentModes[array_rand($paymentModes)];
                $orNumber = 'OR-2026-'.str_pad($i, 4, '0', STR_PAD_LEFT);
                $orDate = $checkIn->toDateString();

                $checkedInAt = $checkIn->copy()->setTime(rand(8, 17), rand(0, 59));
                $checkedOutAt = null;
                $assignmentStatus = 'checked_in';
                if ($status === 'checked_out') {
                    $checkedOutAt = $checkOut->copy()->setTime(rand(8, 12), rand(0, 59));
                    $assignmentStatus = 'checked_out';
                }

                // Room 1 assignment
                RoomAssignment::create([
                    'reservation_id' => $res->id,
                    'guest_id' => $guest->id,
                    'room_id' => $room->id,
                    'assigned_by' => $assigner->id,
                    'assigned_at' => $checkIn->copy()->subDay(),
                    'checked_in_at' => $checkedInAt,
                    'checked_in_by' => $assigner->id,
                    'checked_out_at' => $checkedOutAt,
                    'checked_out_by' => $checkedOutAt ? $staffPool[array_rand($staffPool)]->id : null,
                    'status' => $assignmentStatus,
                    'guest_last_name' => $lastName,
                    'guest_first_name' => $firstName,
                    'guest_middle_initial' => $middleInit,
                    'guest_gender' => $gender,
                    'guest_age' => $age,
                    'guest_full_address' => $address,
                    'guest_contact_number' => $phone,
                    'id_type' => $idType,
                    'id_number' => $idNumber,
                    'is_student' => $isStudent,
                    'is_senior_citizen' => $isSenior,
                    'is_pwd' => $isPwd,
                    'nationality' => $nationality,
                    'purpose_of_stay' => ucfirst($purpose),
                    'num_male_guests' => $numMale,
                    'num_female_guests' => $numFemale,
                    'detailed_checkin_datetime' => $checkedInAt,
                    'detailed_checkout_datetime' => $checkedOutAt,
                    'payment_mode' => $payMode,
                    'payment_amount' => $paymentAmount,
                    'payment_or_number' => $orNumber,
                    'or_date' => $orDate,
                    'remarks' => $isMultiRoom ? "Multi-room reservation #{$i} – Room 1." : "Reservation #{$i}.",
                ]);

                // Room 2 assignment (multi-room)
                if ($isMultiRoom && $roomType2) {
                    $availableRooms2 = $allRoomsByType[$roomType2->id] ?? [];
                    $room2 = $availableRooms2[array_rand($availableRooms2)];
                    // Assign to a companion guest if available, otherwise primary
                    $room2Guest = ! empty($companionGuests) ? $companionGuests[0] : $guest;

                    RoomAssignment::create([
                        'reservation_id' => $res->id,
                        'guest_id' => $room2Guest->id,
                        'room_id' => $room2->id,
                        'assigned_by' => $assigner->id,
                        'assigned_at' => $checkIn->copy()->subDay(),
                        'checked_in_at' => $checkedInAt,
                        'checked_in_by' => $assigner->id,
                        'checked_out_at' => $checkedOutAt,
                        'checked_out_by' => $checkedOutAt ? $staffPool[array_rand($staffPool)]->id : null,
                        'status' => $assignmentStatus,
                        'guest_last_name' => $room2Guest->last_name,
                        'guest_first_name' => $room2Guest->first_name,
                        'guest_middle_initial' => $room2Guest->middle_initial,
                        'guest_gender' => $room2Guest->gender,
                        'guest_age' => $room2Guest->age,
                        'guest_full_address' => $address,
                        'guest_contact_number' => $room2Guest->contact_number ?? $phone,
                        'id_type' => $room2Guest->id_type ?? $idType,
                        'id_number' => $room2Guest->id_number ?? $idNumber,
                        'is_student' => $isStudent,
                        'is_senior_citizen' => $isSenior,
                        'is_pwd' => $isPwd,
                        'nationality' => $nationality,
                        'purpose_of_stay' => ucfirst($purpose),
                        'num_male_guests' => $numMale,
                        'num_female_guests' => $numFemale,
                        'detailed_checkin_datetime' => $checkedInAt,
                        'detailed_checkout_datetime' => $checkedOutAt,
                        'payment_mode' => $payMode,
                        'payment_amount' => 0,
                        'payment_or_number' => $orNumber,
                        'or_date' => $orDate,
                        'remarks' => "Multi-room reservation #{$i} – Room 2.",
                    ]);
                }

                // Room rate charge 1
                ReservationCharge::create([
                    'reservation_id' => $res->id,
                    'charge_type' => 'room_rate',
                    'scope_type' => 'reservation',
                    'scope_id' => $res->id,
                    'description' => $chargeDesc,
                    'qty' => $chargeQty,
                    'unit_price' => $rate,
                    'amount' => $roomCharge,
                    'created_by' => $assigner->id,
                ]);

                // Room rate charge 2 (multi-room)
                if ($isMultiRoom && $roomCharge2 > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $res->id,
                        'charge_type' => 'room_rate',
                        'scope_type' => 'reservation',
                        'scope_id' => $res->id,
                        'description' => $chargeDesc2,
                        'qty' => $chargeQty2,
                        'unit_price' => $rate2,
                        'amount' => $roomCharge2,
                        'created_by' => $assigner->id,
                    ]);
                }

                // Addon charge
                if ($addonTotal > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $res->id,
                        'charge_type' => 'addon',
                        'scope_type' => 'reservation',
                        'scope_id' => $res->id,
                        'description' => $addonName,
                        'qty' => 1,
                        'unit_price' => $addonPrice,
                        'amount' => $addonPrice,
                        'meta' => ['service_code' => $addonCode],
                        'created_by' => $assigner->id,
                    ]);
                }

                // Discount charge
                if ($discountAmount > 0) {
                    ReservationCharge::create([
                        'reservation_id' => $res->id,
                        'charge_type' => 'discount',
                        'scope_type' => 'reservation',
                        'scope_id' => $res->id,
                        'description' => 'Discount: '.implode(' + ', $discountTypes),
                        'qty' => 1,
                        'unit_price' => -$discountAmount,
                        'amount' => -$discountAmount,
                        'meta' => [
                            'discount_types' => $discountTypes,
                            'discount_percent' => $discountPercent,
                            'subtotal_before_discount' => $totalRoomCharge,
                        ],
                        'created_by' => $assigner->id,
                    ]);
                }

                // Payment record
                if ($paymentAmount > 0) {
                    ReservationPayment::create([
                        'reservation_id' => $res->id,
                        'amount' => $paymentAmount,
                        'payment_mode' => $payMode,
                        'reference_no' => $orNumber,
                        'or_date' => $orDate,
                        'status' => 'posted',
                        'received_by' => $assigner->id,
                        'received_at' => $checkedInAt,
                        'remarks' => $paymentStatus === 'paid' ? 'Full payment' : 'Partial payment',
                    ]);
                }

                // Check-in snapshot
                CheckInSnapshot::create([
                    'reservation_id' => $res->id,
                    'guest_id' => $guest->id,
                    'id_type' => $idType,
                    'id_number' => $idNumber,
                    'nationality' => $nationality,
                    'purpose_of_stay' => ucfirst($purpose),
                    'detailed_checkin_datetime' => $checkedInAt,
                    'detailed_checkout_datetime' => $checkedOutAt,
                    'payment_mode' => $payMode,
                    'payment_amount' => $paymentAmount,
                    'payment_or_number' => $orNumber,
                    'or_date' => $orDate,
                    'additional_requests' => $addonCode ? [$addonCode] : [],
                    'remarks' => $isMultiRoom ? "Multi-room reservation #{$i}" : "Reservation #{$i}",
                    'captured_by' => $assigner->id,
                    'captured_at' => $checkedInAt,
                ]);
            }
        }

        // ─── Reservation Sequence ────────────────────────────────────────
        DB::table('reservation_sequences')->updateOrInsert(
            ['year' => Carbon::today()->year],
            ['last_sequence' => 1000],
        );
    }
}
