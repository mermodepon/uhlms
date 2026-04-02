<?php

namespace Tests\Unit\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\ServicePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ServicePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ServicePolicy;
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
        $service = new Service;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $service));
        $this->assertTrue($this->policy->delete($user, $service));
    }

    public function test_admin_has_full_service_access(): void
    {
        $user = $this->createUser('admin');
        $service = new Service;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $service));
        $this->assertTrue($this->policy->delete($user, $service));
    }

    public function test_staff_can_view_but_not_modify(): void
    {
        $user = $this->createUser('staff');
        $service = new Service;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $service));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, $service));
        $this->assertFalse($this->policy->delete($user, $service));
    }
}
