<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FloorResource\Pages\CreateFloor;
use App\Filament\Resources\FloorResource\Pages\EditFloor;
use App\Filament\Resources\FloorResource\Pages\ListFloors;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FloorResourceTest extends TestCase
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

        Livewire::test(ListFloors::class)->assertSuccessful();
    }

    public function test_admin_can_render_create_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateFloor::class)->assertSuccessful();
    }

    public function test_admin_can_create_floor(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateFloor::class)
            ->fillForm([
                'name' => '1st Floor',
                'level' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('floors', ['name' => '1st Floor', 'level' => 1]);
    }

    public function test_create_requires_name_and_level(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateFloor::class)
            ->fillForm([
                'name' => '',
                'level' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required', 'level' => 'required']);
    }

    public function test_level_must_be_unique(): void
    {
        $this->actingAs($this->admin);

        Floor::create(['name' => 'Ground', 'level' => 1, 'is_active' => true]);

        Livewire::test(CreateFloor::class)
            ->fillForm([
                'name' => 'Another',
                'level' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['level' => 'unique']);
    }

    public function test_admin_can_edit_floor(): void
    {
        $this->actingAs($this->admin);

        $floor = Floor::create(['name' => 'Old Floor', 'level' => 5, 'is_active' => true]);

        Livewire::test(EditFloor::class, ['record' => $floor->getRouteKey()])
            ->fillForm(['name' => 'Renamed Floor'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals('Renamed Floor', $floor->fresh()->name);
    }

    public function test_list_page_shows_floors(): void
    {
        $this->actingAs($this->admin);

        Floor::create(['name' => 'Basement', 'level' => 0, 'is_active' => true]);
        Floor::create(['name' => 'Top Floor', 'level' => 10, 'is_active' => true]);

        Livewire::test(ListFloors::class)
            ->assertCanSeeTableRecords(Floor::all());
    }
}
