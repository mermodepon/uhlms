<?php

namespace Tests\Unit\Models;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $service = new Service;
        $fillable = $service->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('category', $fillable);
        $this->assertContains('price', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('sort_order', $fillable);
    }

    public function test_casts(): void
    {
        $service = new Service;
        $casts = $service->getCasts();

        $this->assertEquals('decimal:2', $casts['price']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_auto_generates_code_from_name(): void
    {
        $service = Service::create([
            'name' => 'Extra Towels',
            'price' => 50,
            'is_active' => true,
        ]);

        $this->assertEquals('extra-towels', $service->code);
    }

    public function test_auto_generates_unique_code(): void
    {
        Service::create(['name' => 'Laundry', 'code' => 'laundry', 'price' => 100, 'is_active' => true]);
        $service2 = Service::create(['name' => 'Laundry', 'price' => 150, 'is_active' => true]);

        $this->assertEquals('laundry-1', $service2->code);
    }

    public function test_preserves_custom_code(): void
    {
        $service = Service::create([
            'name' => 'Room Service',
            'code' => 'custom-code',
            'price' => 200,
            'is_active' => true,
        ]);

        $this->assertEquals('custom-code', $service->code);
    }

    public function test_active_scope(): void
    {
        Service::create(['name' => 'Active Service', 'price' => 50, 'is_active' => true]);
        Service::create(['name' => 'Inactive Service', 'price' => 50, 'is_active' => false]);

        $this->assertEquals(1, Service::active()->count());
    }

    public function test_ordered_scope(): void
    {
        Service::create(['name' => 'Beta', 'price' => 50, 'is_active' => true, 'sort_order' => 2]);
        Service::create(['name' => 'Alpha', 'price' => 50, 'is_active' => true, 'sort_order' => 1]);

        $services = Service::ordered()->get();
        $this->assertEquals('Alpha', $services->first()->name);
    }

    public function test_formatted_price_with_value(): void
    {
        $service = Service::create(['name' => 'Pool', 'price' => 150.50, 'is_active' => true]);

        $this->assertEquals('₱150.50', $service->formatted_price);
    }

    public function test_formatted_price_free(): void
    {
        $service = Service::create(['name' => 'WiFi', 'price' => 0, 'is_active' => true]);

        $this->assertEquals('Free', $service->formatted_price);
    }
}
