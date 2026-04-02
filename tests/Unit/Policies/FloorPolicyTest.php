<?php

namespace Tests\Unit\Policies;

use App\Models\Floor;
use App\Models\User;
use App\Policies\FloorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FloorPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FloorPolicy;
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
        $floor = new Floor;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $floor));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $floor));
        $this->assertTrue($this->policy->delete($user, $floor));
    }

    public function test_admin_has_full_floor_access(): void
    {
        $user = $this->createUser('admin');
        $floor = new Floor;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->delete($user, $floor));
    }

    public function test_staff_can_view_but_not_modify(): void
    {
        $user = $this->createUser('staff');
        $floor = new Floor;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $floor));
        $this->assertFalse($this->policy->delete($user, $floor));
    }
}
