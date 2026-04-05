<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);
    }

    public function test_admin_can_render_list_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListServices::class)->assertSuccessful();
    }

    public function test_admin_can_render_create_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateService::class)->assertSuccessful();
    }

    public function test_admin_can_create_service(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Extra Pillows',
                'price' => 100.00,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', ['name' => 'Extra Pillows']);
    }

    public function test_create_requires_name(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateService::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_admin_can_edit_service(): void
    {
        $this->actingAs($this->admin);

        $service = Service::create(['name' => 'Laundry', 'price' => 50, 'is_active' => true]);

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['name' => 'Premium Laundry'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Premium Laundry', $service->fresh()->name);
    }

    public function test_list_page_shows_services(): void
    {
        $this->actingAs($this->admin);

        Service::create(['name' => 'WiFi', 'price' => 0, 'is_active' => true]);
        Service::create(['name' => 'Parking', 'price' => 200, 'is_active' => true]);

        Livewire::test(ListServices::class)
            ->assertCanSeeTableRecords(Service::all());
    }
}
