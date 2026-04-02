<?php

namespace Tests\Unit\Policies;

use App\Models\Amenity;
use App\Models\User;
use App\Policies\AmenityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmenityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AmenityPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AmenityPolicy;
    }

    private function createUser(string $role, ?array $permissions = null): User
    {
        return User::create([
            'name' => 'User',
            'email' => $role . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    public function test_super_admin_can_do_everything(): void
    {
        $user = $this->createUser('super_admin');
        $amenity = new Amenity;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $amenity));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $amenity));
        $this->assertTrue($this->policy->delete($user, $amenity));
        $this->assertTrue($this->policy->deleteAny($user));
    }

    public function test_admin_has_full_amenity_access(): void
    {
        $user = $this->createUser('admin');
        $amenity = new Amenity;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $amenity));
        $this->assertTrue($this->policy->delete($user, $amenity));
    }

    public function test_staff_can_view_but_not_modify(): void
    {
        $user = $this->createUser('staff');
        $amenity = new Amenity;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $amenity));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $amenity));
        $this->assertFalse($this->policy->delete($user, $amenity));
    }

    public function test_custom_permissions_override_defaults(): void
    {
        $user = $this->createUser('staff', [
            'amenities_view' => false,
            'amenities_create' => true,
        ]);
        $amenity = new Amenity;

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
    }
}
