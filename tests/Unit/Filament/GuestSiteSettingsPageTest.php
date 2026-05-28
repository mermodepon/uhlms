<?php

namespace Tests\Unit\Filament;

use App\Filament\Pages\GuestSiteSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Settings User',
            'email' => 'settings-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'permissions' => null,
        ], $overrides));
    }

    public function test_admin_can_view_and_edit_guest_site_settings(): void
    {
        $this->actingAs($this->user(['role' => 'admin']));

        $this->assertTrue(GuestSiteSettings::canAccess());
        $this->assertTrue(GuestSiteSettings::canEdit());
    }

    public function test_staff_without_permission_cannot_access_guest_site_settings(): void
    {
        $this->actingAs($this->user());

        $this->assertFalse(GuestSiteSettings::canAccess());
        $this->assertFalse(GuestSiteSettings::canEdit());
    }

    public function test_read_only_user_can_view_but_not_edit_guest_site_settings(): void
    {
        $this->actingAs($this->user([
            'permissions' => [
                'guest_site_settings_view' => true,
                'guest_site_settings_edit' => false,
            ],
        ]));

        $this->assertTrue(GuestSiteSettings::canAccess());
        $this->assertFalse(GuestSiteSettings::canEdit());
    }
}
