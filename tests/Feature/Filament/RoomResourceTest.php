<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\RoomResource\Pages\CreateRoom;
use App\Filament\Resources\RoomResource\Pages\EditRoom;
use App\Filament\Resources\RoomResource\Pages\ListRooms;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private RoomType $roomType;
    private Floor $floor;

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

        $this->roomType = RoomType::create([
            'name' => 'Standard',
            'base_rate' => 500,
            'pricing_type' => 'flat_rate',
            'room_sharing_type' => 'private',
            'is_active' => true,
        ]);

        $this->floor = Floor::create([
            'name' => 'Ground Floor',
            'level' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_render_list_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListRooms::class)->assertSuccessful();
    }

    public function test_admin_can_render_create_page(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateRoom::class)->assertSuccessful();
    }

    public function test_admin_can_create_room(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateRoom::class)
            ->fillForm([
                'room_number' => '101',
                'room_type_id' => $this->roomType->id,
                'floor_id' => $this->floor->id,
                'capacity' => 4,
                'status' => 'available',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rooms', ['room_number' => '101']);
    }

    public function test_create_requires_room_number(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateRoom::class)
            ->fillForm([
                'room_number' => '',
                'room_type_id' => $this->roomType->id,
                'floor_id' => $this->floor->id,
                'capacity' => 4,
                'status' => 'available',
            ])
            ->call('create')
            ->assertHasFormErrors(['room_number' => 'required']);
    }

    public function test_room_number_must_be_unique(): void
    {
        $this->actingAs($this->admin);

        Room::create([
            'room_number' => '101',
            'room_type_id' => $this->roomType->id,
            'floor_id' => $this->floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        Livewire::test(CreateRoom::class)
            ->fillForm([
                'room_number' => '101',
                'room_type_id' => $this->roomType->id,
                'floor_id' => $this->floor->id,
                'capacity' => 4,
                'status' => 'available',
            ])
            ->call('create')
            ->assertHasFormErrors(['room_number' => 'unique']);
    }

    public function test_admin_can_edit_room(): void
    {
        $this->actingAs($this->admin);

        $room = Room::create([
            'room_number' => '202',
            'room_type_id' => $this->roomType->id,
            'floor_id' => $this->floor->id,
            'capacity' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        Livewire::test(EditRoom::class, ['record' => $room->getRouteKey()])
            ->fillForm(['capacity' => 6])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(6, $room->fresh()->capacity);
    }

    public function test_list_page_shows_rooms(): void
    {
        $this->actingAs($this->admin);

        Room::create([
            'room_number' => '301',
            'room_type_id' => $this->roomType->id,
            'floor_id' => $this->floor->id,
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        Livewire::test(ListRooms::class)
            ->assertCanSeeTableRecords(Room::all());
    }
}
