<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SecurityAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('file')->flush();
        Notification::fake();
        config()->set('security.monitoring.enabled', true);
        config()->set('security.monitoring.response_threshold', 2);
        config()->set('security.monitoring.response_window_seconds', 300);
        Route::get('/_test/restricted', fn () => abort(403))->name('test.restricted');
    }

    public function test_repeated_failures_are_aggregated_without_storing_the_raw_ip(): void
    {
        $superAdmin = User::create([
            'name' => 'Security Admin',
            'email' => 'security-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        $offset = is_file($log) ? filesize($log) : 0;

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.251'])->get('/_test/restricted')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.251'])->get('/_test/restricted')->assertForbidden();

        Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
        $newLog = is_file($log) ? substr((string) file_get_contents($log), $offset) : '';
        $this->assertStringContainsString('security_threshold_reached', $newLog);
        $this->assertStringNotContainsString('203.0.113.251', $newLog);
    }
}
