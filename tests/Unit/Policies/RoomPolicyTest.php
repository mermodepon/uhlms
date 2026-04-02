<?php

namespace Tests\Unit\Policies;

use App\Models\Room;
use App\Models\User;
use App\Policies\RoomPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RoomPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RoomPolicy;
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => 'User',
            'email' => $role . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_super_admin_can_do_everything(): void
    {
        $user = $this->createUser('super_admin');
        $room = new Room;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $room));
        $this->assertTrue($this->policy->delete($user, $room));
    }

    public function test_admin_has_full_room_access(): void
    {
        $user = $this->createUser('admin');
        $room = new Room;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $room));
        $this->assertTrue($this->policy->delete($user, $room));
    }

    public function test_staff_can_view_but_not_modify(): void
    {
        $user = $this->createUser('staff');
        $room = new Room;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $room));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $room));
        $this->assertFalse($this->policy->delete($user, $room));
    }
}
