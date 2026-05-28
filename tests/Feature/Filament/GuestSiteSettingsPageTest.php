<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_guest_site_settings_page(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'permissions' => null,
        ]);

        $this->actingAs($admin)
            ->get('/admin/guest-site-settings')
            ->assertOk()
            ->assertSee('Guest Site Settings');
    }

    public function test_staff_without_permission_cannot_render_guest_site_settings_page(): void
    {
        $staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => null,
        ]);

        $this->actingAs($staff)
            ->get('/admin/guest-site-settings')
            ->assertForbidden();
    }
}
