<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupRestore;
use App\Jobs\RestoreDatabaseJob;
use App\Models\User;
use App\Services\EncryptedBackupService;
use App\Support\AdminMfa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Livewire;
use Tests\TestCase;

class BackupRestoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('security.backups.encryption_key', base64_encode(random_bytes(32)));
        Notification::fake();
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/backups/*test*.uhlmsbak')) ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob(storage_path('app/backups/legacy_*.uhlmsbak')) ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob(storage_path('app/backups/uploaded_*.uhlmsbak')) ?: [] as $file) {
            @unlink($file);
        }
        @unlink(storage_path('app/backups/_restore_running.txt'));
        @unlink(storage_path('app/backups/_restore_status.txt'));
        parent::tearDown();
    }

    public function test_backup_page_requires_super_admin_with_recent_mfa(): void
    {
        $superAdmin = $this->mfaUser('super_admin');

        $this->actingAs($superAdmin)->get('/admin/backup-restore')
            ->assertRedirect(route('admin.mfa.recent', ['intended' => '/admin/backup-restore']));

        $this->actingAs($this->mfaUser('staff'))
            ->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp])
            ->get('/admin/backup-restore')
            ->assertRedirect('/admin/login');

        $this->actingAs($superAdmin)
            ->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp])
            ->get('/admin/backup-restore')
            ->assertOk()
            ->assertSee('Backup & Restore');
    }

    public function test_web_upload_accepts_only_authenticated_encrypted_backups(): void
    {
        $user = $this->mfaUser('super_admin');

        $this->actingAs($user)
            ->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp])
            ->post(route('backup.upload'), [
                'backup_file' => UploadedFile::fake()->createWithContent('backup.sql', '-- UHLMS Database Backup'),
            ])
            ->assertSessionHasErrors('backup_file');

        $this->actingAs($user)
            ->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp])
            ->post(route('backup.upload'), [
                'backup_file' => UploadedFile::fake()->createWithContent('backup.uhlmsbak', 'tampered'),
            ])
            ->assertSessionHas('upload_error');
    }

    public function test_stale_mfa_cannot_invoke_livewire_backup_actions(): void
    {
        $this->actingAs($this->mfaUser('super_admin'))
            ->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp]);

        $component = Livewire::test(BackupRestore::class);
        session()->forget(AdminMfa::RECENT_SESSION_KEY);

        $component->call('createBackup')
            ->assertForbidden();
    }

    public function test_restore_still_requires_phrase_and_current_password_before_dispatch(): void
    {
        Bus::fake();
        $user = $this->mfaUser('super_admin');
        $filename = $this->encryptedLegacyBackup();
        $this->actingAs($user)->withSession([AdminMfa::RECENT_SESSION_KEY => now()->timestamp]);

        Livewire::test(BackupRestore::class)
            ->set('restoreExistingFilename', $filename)
            ->set('confirmPhrase', 'RESTORE')
            ->set('restorePassword', 'wrong-password')
            ->call('executeRestoreExisting')
            ->assertHasErrors(['restorePassword']);
        Bus::assertNotDispatched(RestoreDatabaseJob::class);

        Livewire::test(BackupRestore::class)
            ->set('restoreExistingFilename', $filename)
            ->set('confirmPhrase', 'RESTORE')
            ->set('restorePassword', 'password')
            ->call('executeRestoreExisting')
            ->assertHasNoErrors();
        Bus::assertDispatched(RestoreDatabaseJob::class);
    }

    private function mfaUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->refresh();
    }

    private function encryptedLegacyBackup(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uhlms-legacy-');
        file_put_contents($path, "-- UHLMS Database Backup\nCREATE TABLE `phase9_test` (`id` int);\n");
        try {
            return app(EncryptedBackupService::class)->importLegacySql($path, 'security_test');
        } finally {
            @unlink($path);
        }
    }
}
