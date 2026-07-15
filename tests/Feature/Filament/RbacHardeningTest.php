<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\FeedbackAnalyticsReport;
use App\Filament\Pages\OccupancyReport;
use App\Filament\Pages\Reports;
use App\Filament\Pages\RoomUtilizationCalendar;
use App\Filament\Resources\RoomHoldResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\PermissionsReference;
use App\Filament\Resources\VirtualTourResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RbacHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role, ?array $permissions = null): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    public function test_staff_default_access_is_operational_only(): void
    {
        $staff = $this->createUser('staff');

        $this->assertFalse($staff->hasPermission(User::REPORT_MONTHLY_VIEW));
        $this->assertTrue($staff->hasPermission(User::REPORT_RESERVATION_LIST_VIEW));
        $this->assertTrue($staff->hasPermission(User::REPORT_OCCUPANCY_VIEW));
        $this->assertFalse($staff->hasPermission(User::REPORT_FEEDBACK_ANALYTICS_VIEW));
        $this->assertTrue($staff->hasPermission(User::ROOM_HOLDS_VIEW));
        $this->assertFalse($staff->hasPermission(User::ROOM_HOLDS_RELEASE));
        $this->assertFalse($staff->hasPermission(User::VIRTUAL_TOUR_VIEW));
    }

    public function test_admin_and_super_admin_receive_full_new_access(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->createUser($role);

            foreach ([
                User::REPORT_MONTHLY_VIEW,
                User::REPORT_MONTHLY_EXPORT,
                User::REPORT_STAY_LOGS_VIEW,
                User::ROOM_HOLDS_RELEASE,
                User::VIRTUAL_TOUR_MANAGE,
            ] as $permission) {
                $this->assertTrue($user->hasPermission($permission), "{$role} should have {$permission}");
            }
        }
    }

    public function test_sensitive_report_routes_reject_staff_without_permission(): void
    {
        $staff = $this->createUser('staff');
        $this->actingAs($staff);

        foreach ([
            '/admin/reports',
            '/admin/reports/feedback-analytics',
            '/admin/reports/gender-statistics',
            '/admin/reports/stay-logs',
        ] as $uri) {
            $this->get($uri)->assertForbidden();
        }

        $this->get('/admin/reports/reservation-list')->assertOk();
        $this->get('/admin/reports/reservation-summary')->assertOk();
        $this->get('/admin/reports/occupancy')->assertOk();
        $this->get('/admin/reports/room-utilization')->assertOk();
        $this->get('/admin/room-utilization-calendar')->assertOk();
    }

    public function test_custom_report_permission_grants_only_the_selected_report(): void
    {
        $staff = $this->createUser('staff', [
            User::REPORT_FEEDBACK_ANALYTICS_VIEW => true,
        ]);

        $this->actingAs($staff);

        $this->assertTrue(FeedbackAnalyticsReport::canAccess());
        $this->assertFalse(Reports::canAccess());
        $this->assertFalse(OccupancyReport::canAccess());
        $this->assertFalse(RoomUtilizationCalendar::canAccess());
    }

    public function test_feedback_analytics_uses_the_varied_cmu_chart_palette(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->get('/admin/reports/feedback-analytics')
            ->assertOk()
            ->assertSee('#00491E', false)
            ->assertSee('#087F5B', false)
            ->assertSee('#0F766E', false)
            ->assertSee('#919F02', false)
            ->assertSee('#D6A800', false);
    }

    public function test_monthly_export_requires_a_separate_permission(): void
    {
        $staff = $this->createUser('staff', [
            User::REPORT_MONTHLY_VIEW => true,
        ]);

        $this->actingAs($staff);
        $this->expectException(HttpException::class);

        (new Reports)->downloadMonthlyReportExcel();
    }

    public function test_room_holds_and_virtual_tour_require_explicit_access(): void
    {
        $staff = $this->createUser('staff');
        $this->actingAs($staff);

        $this->assertTrue(RoomHoldResource::canAccess());
        $this->assertFalse(VirtualTourResource::canAccess());
        $this->get('/admin/room-holds')->assertOk();
        $this->get('/admin/virtual-tour')->assertForbidden();
    }

    public function test_custom_module_permissions_are_honored(): void
    {
        $staff = $this->createUser('staff', [
            User::ROOM_HOLDS_VIEW => true,
            User::ROOM_HOLDS_RELEASE => true,
            User::VIRTUAL_TOUR_VIEW => true,
            User::VIRTUAL_TOUR_MANAGE => true,
        ]);

        $this->actingAs($staff);

        $this->assertTrue(RoomHoldResource::canAccess());
        $this->assertTrue(VirtualTourResource::canAccess());
        $this->assertTrue(VirtualTourResource::canCreate());
        $this->assertTrue(VirtualTourResource::canEdit(null));
        $this->assertTrue(VirtualTourResource::canDelete(null));
    }

    public function test_permissions_reference_is_nested_under_users(): void
    {
        $admin = $this->createUser('admin');
        $this->actingAs($admin);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('Roles &amp; Permissions', false);

        $this->get('/admin/users/permissions')
            ->assertOk()
            ->assertSee('Roles &amp; Permissions', false)
            ->assertSee('Users');

        $this->assertStringContainsString('/admin/users', UserResource::getUrl('permissions'));
        $this->assertSame('Roles & Permissions', (new PermissionsReference)->getTitle());
        $this->assertSame('Users', array_values((new PermissionsReference)->getBreadcrumbs())[0]);

        $staff = $this->createUser('staff');
        $this->actingAs($staff)
            ->get('/admin/users/permissions')
            ->assertRedirect();
    }

    public function test_super_admin_can_reset_custom_permissions_to_role_defaults(): void
    {
        $superAdmin = $this->createUser('super_admin');
        $target = $this->createUser('staff', [
            'reservations_view' => false,
            'reservations_create' => true,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->callAction('reset_permissions')
            ->assertSuccessful();

        $this->assertNull($target->refresh()->permissions);
        $this->assertTrue($target->hasPermission('reservations_view'));
    }
}
