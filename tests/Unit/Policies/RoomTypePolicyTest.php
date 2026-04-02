<?php

namespace Tests\Unit\Policies;

use App\Models\RoomType;
use App\Models\User;
use App\Policies\RoomTypePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypePolicyTest extends TestCase
{
    use RefreshDatabase;

    private RoomTypePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RoomTypePolicy;
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
        $roomType = new RoomType;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $roomType));
        $this->assertTrue($this->policy->delete($user, $roomType));
    }

    public function test_admin_has_full_room_type_access(): void
    {
        $user = $this->createUser('admin');
        $roomType = new RoomType;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->delete($user, $roomType));
    }

    public function test_staff_can_view_but_not_modify(): void
    {
        $user = $this->createUser('staff');
        $roomType = new RoomType;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $roomType));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $roomType));
        $this->assertFalse($this->policy->delete($user, $roomType));
    }
}
