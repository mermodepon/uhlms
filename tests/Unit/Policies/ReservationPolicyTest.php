<?php

namespace Tests\Unit\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Policies\ReservationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ReservationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ReservationPolicy;
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
        $reservation = new Reservation;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $reservation));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $reservation));
        $this->assertTrue($this->policy->delete($user, $reservation));
    }

    public function test_admin_has_full_reservation_access(): void
    {
        $user = $this->createUser('admin');
        $reservation = new Reservation;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $reservation));
        $this->assertTrue($this->policy->delete($user, $reservation));
    }

    public function test_staff_can_view_create_edit_but_not_delete(): void
    {
        $user = $this->createUser('staff');
        $reservation = new Reservation;

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->create($user));
        $this->assertTrue($this->policy->update($user, $reservation));
        $this->assertFalse($this->policy->delete($user, $reservation));
        $this->assertFalse($this->policy->deleteAny($user));
    }
}
