<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AmenityResource;
use App\Filament\Resources\AmenityResource\Pages\CreateAmenity;
use App\Filament\Resources\AmenityResource\Pages\EditAmenity;
use App\Filament\Resources\AmenityResource\Pages\ListAmenities;
use App\Models\Amenity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AmenityResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;

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

        $this->staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => null,
        ]);
    }

    public function test_admin_can_render_list_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListAmenities::class)->assertSuccessful();
    }

    public function test_admin_can_render_create_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAmenity::class)->assertSuccessful();
    }

    public function test_admin_can_create_amenity(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAmenity::class)
            ->fillForm([
                'name' => 'Swimming Pool',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('amenities', ['name' => 'Swimming Pool']);
    }

    public function test_create_requires_name(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateAmenity::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_admin_can_edit_amenity(): void
    {
        $this->actingAs($this->admin);

        $amenity = Amenity::create(['name' => 'Old Name', 'is_active' => true]);

        Livewire::test(EditAmenity::class, ['record' => $amenity->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Updated Name', $amenity->fresh()->name);
    }

    public function test_list_page_shows_amenities(): void
    {
        $this->actingAs($this->admin);

        Amenity::create(['name' => 'WiFi', 'is_active' => true]);
        Amenity::create(['name' => 'Parking', 'is_active' => false]);

        Livewire::test(ListAmenities::class)
            ->assertCanSeeTableRecords(Amenity::all());
    }
}
