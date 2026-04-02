<?php

namespace Tests\Unit\Models;

use App\Models\Notification;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ], $overrides));
    }

    public function test_fillable_attributes(): void
    {
        $user = new User;
        $fillable = $user->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('role', $fillable);
        $this->assertContains('permissions', $fillable);
    }

    public function test_hidden_attributes(): void
    {
        $user = new User;
        $hidden = $user->getHidden();

        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    public function test_casts(): void
    {
        $user = new User;
        $casts = $user->getCasts();

        $this->assertEquals('datetime', $casts['email_verified_at']);
        $this->assertEquals('hashed', $casts['password']);
        $this->assertEquals('array', $casts['permissions']);
    }

    public function test_is_super_admin(): void
    {
        $superAdmin = $this->createUser(['role' => 'super_admin']);
        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($admin->isSuperAdmin());
    }

    public function test_is_admin(): void
    {
        $superAdmin = $this->createUser(['role' => 'super_admin']);
        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin@example.com']);
        $staff = $this->createUser(['role' => 'staff', 'email' => 'staff@example.com']);

        $this->assertTrue($superAdmin->isAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($staff->isAdmin());
    }

    public function test_is_staff(): void
    {
        $superAdmin = $this->createUser(['role' => 'super_admin']);
        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin@example.com']);
        $staff = $this->createUser(['role' => 'staff', 'email' => 'staff@example.com']);

        $this->assertTrue($superAdmin->isStaff());
        $this->assertTrue($admin->isStaff());
        $this->assertTrue($staff->isStaff());
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $superAdmin = $this->createUser(['role' => 'super_admin']);

        $this->assertTrue($superAdmin->hasPermission('reservations_view'));
        $this->assertTrue($superAdmin->hasPermission('users_delete'));
        $this->assertTrue($superAdmin->hasPermission('any_random_permission'));
    }

    public function test_staff_default_permissions(): void
    {
        $staff = $this->createUser(['role' => 'staff']);

        $this->assertTrue($staff->hasPermission('reservations_view'));
        $this->assertTrue($staff->hasPermission('reservations_create'));
        $this->assertFalse($staff->hasPermission('reservations_delete'));
        $this->assertFalse($staff->hasPermission('users_view'));
        $this->assertFalse($staff->hasPermission('rooms_create'));
    }

    public function test_admin_default_permissions(): void
    {
        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin@example.com']);

        $this->assertTrue($admin->hasPermission('reservations_view'));
        $this->assertTrue($admin->hasPermission('reservations_delete'));
        $this->assertTrue($admin->hasPermission('users_view'));
        $this->assertTrue($admin->hasPermission('users_create'));
        $this->assertTrue($admin->hasPermission('rooms_create'));
    }

    public function test_custom_permissions_override_role_defaults(): void
    {
        $staff = $this->createUser([
            'role' => 'staff',
            'permissions' => [
                'reservations_view' => false,
                'users_delete' => true,
            ],
        ]);

        // Custom permissions override the role default (which is true for reservations_view)
        $this->assertFalse($staff->hasPermission('reservations_view'));
        // Custom permissions override the role default (which is false for users_delete)
        $this->assertTrue($staff->hasPermission('users_delete'));
        // Keys not in custom permissions return false
        $this->assertFalse($staff->hasPermission('rooms_create'));
    }

    public function test_default_permissions_for_role(): void
    {
        $staffDefaults = User::defaultPermissionsForRole('staff');
        $adminDefaults = User::defaultPermissionsForRole('admin');
        $unknownDefaults = User::defaultPermissionsForRole('unknown_role');

        $this->assertIsArray($staffDefaults);
        $this->assertIsArray($adminDefaults);
        $this->assertTrue($adminDefaults['reservations_delete']);
        $this->assertFalse($staffDefaults['reservations_delete']);
        // Unknown role falls back to staff
        $this->assertEquals($staffDefaults, $unknownDefaults);
    }

    public function test_can_access_panel(): void
    {
        $panel = $this->createMock(\Filament\Panel::class);

        $superAdmin = $this->createUser(['role' => 'super_admin']);
        $admin = $this->createUser(['role' => 'admin', 'email' => 'admin@example.com']);
        $staff = $this->createUser(['role' => 'staff', 'email' => 'staff@example.com']);

        $this->assertTrue($superAdmin->canAccessPanel($panel));
        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertTrue($staff->canAccessPanel($panel));
    }

    public function test_reviewed_reservations_relationship(): void
    {
        $user = new User;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->reviewedReservations()
        );
    }

    public function test_room_assignments_relationship(): void
    {
        $user = new User;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $user->roomAssignments()
        );
    }

    public function test_notifications_relationship(): void
    {
        $user = new User;
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\MorphMany::class,
            $user->notifications()
        );
    }

    public function test_unread_notification_count(): void
    {
        $user = $this->createUser();

        Notification::create([
            'title' => 'Read',
            'message' => 'msg',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => true,
        ]);

        Notification::create([
            'title' => 'Unread',
            'message' => 'msg',
            'type' => 'info',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'is_read' => false,
        ]);

        $this->assertEquals(1, $user->unread_notification_count);
    }
}
