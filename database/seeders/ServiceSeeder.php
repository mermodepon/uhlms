<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
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
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(
                ['code' => $service['code']],
                $service
            );
        }

        $this->command->info('Services seeded successfully!');
    }
}

