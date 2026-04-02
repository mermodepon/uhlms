<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
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

    public function test_super_admin_can_view_any_user(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->assertTrue($this->policy->view($superAdmin, $admin));
        $this->assertTrue($this->policy->view($superAdmin, $staff));
    }

    public function test_admin_can_only_view_staff(): void
    {
        $admin = $this->createUser('admin');
        $otherAdmin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->assertTrue($this->policy->view($admin, $staff));
        // Admin cannot view other admins (prevents privilege inspection)
        $this->assertFalse($this->policy->view($admin, $otherAdmin));
    }

    public function test_super_admin_can_update_anyone(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->assertTrue($this->policy->update($superAdmin, $admin));
        $this->assertTrue($this->policy->update($superAdmin, $staff));
    }

    public function test_admin_can_only_update_staff(): void
    {
        $admin = $this->createUser('admin');
        $otherAdmin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->assertTrue($this->policy->update($admin, $staff));
        $this->assertFalse($this->policy->update($admin, $otherAdmin));
    }

    public function test_self_edit_only_requires_edit_permission(): void
    {
        $staff = $this->createUser('staff');
        // Staff doesn't have users_edit by default
        $this->assertFalse($this->policy->update($staff, $staff));

        $staffWithEdit = User::create([
            'name' => 'Staff',
            'email' => 'staffedit@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => ['users_edit' => true],
        ]);
        $this->assertTrue($this->policy->update($staffWithEdit, $staffWithEdit));
    }

    public function test_cannot_delete_self(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $this->assertFalse($this->policy->delete($superAdmin, $superAdmin));
    }

    public function test_cannot_force_delete_self(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $this->assertFalse($this->policy->forceDelete($superAdmin, $superAdmin));
    }

    public function test_admin_can_delete_other_users(): void
    {
        $admin = $this->createUser('admin');
        $staff = $this->createUser('staff');

        $this->assertTrue($this->policy->delete($admin, $staff));
    }

    public function test_staff_without_permission_cannot_delete(): void
    {
        $staff = $this->createUser('staff');
        $otherStaff = $this->createUser('staff');

        $this->assertFalse($this->policy->delete($staff, $otherStaff));
    }

    public function test_staff_cannot_view_users(): void
    {
        $staff = $this->createUser('staff');
        $this->assertFalse($this->policy->viewAny($staff));
    }

    public function test_admin_can_create_users(): void
    {
        $admin = $this->createUser('admin');
        $this->assertTrue($this->policy->create($admin));
    }

    public function test_staff_cannot_create_users(): void
    {
        $staff = $this->createUser('staff');
        $this->assertFalse($this->policy->create($staff));
    }
}
