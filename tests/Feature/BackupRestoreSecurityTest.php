<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupRestore;
use App\Jobs\RestoreDatabaseJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class BackupRestoreSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/backups/security_test*.sql')) ?: [] as $file) {
            @unlink($file);
        }

        foreach (glob(storage_path('app/backups/uploaded_*.sql')) ?: [] as $file) {
            @unlink($file);
        }

        @unlink(storage_path('app/backups/_restore_running.txt'));
        @unlink(storage_path('app/backups/_restore_status.txt'));

        parent::tearDown();
    }

    public function test_super_admin_can_render_backup_restore_page(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->get('/admin/backup-restore')
            ->assertOk()
            ->assertSee('Backup & Restore');
    }

    public function test_staff_cannot_render_backup_restore_page(): void
    {
        $this->actingAs($this->user('staff'))
            ->get('/admin/backup-restore')
            ->assertForbidden();
    }

    public function test_backup_upload_rejects_invalid_extension(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->post(route('backup.upload'), [
                'backup_file' => $this->uploadedFile('backup.txt', 'CREATE TABLE `demo` (`id` int);'),
            ])
            ->assertSessionHas('upload_error', 'Only .sql files are accepted.');
    }

    public function test_backup_upload_rejects_script_like_content(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->post(route('backup.upload'), [
                'backup_file' => $this->uploadedFile('backup.sql', '<?php echo "owned";'),
            ])
            ->assertSessionHas('upload_error', 'File does not look like a supported SQL backup.');
    }

    public function test_backup_upload_rejects_oversized_file(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->post(route('backup.upload'), [
                'backup_file' => UploadedFile::fake()->create('backup.sql', 51201, 'application/sql'),
            ])
            ->assertSessionHasErrors('backup_file');
    }

    public function test_backup_upload_accepts_sql_looking_backup(): void
    {
        $this->actingAs($this->user('super_admin'))
            ->post(route('backup.upload'), [
                'backup_file' => $this->uploadedFile('backup.sql', 'CREATE TABLE `demo` (`id` int);'),
            ])
            ->assertSessionHas('upload_success');

        $this->assertNotEmpty(glob(storage_path('app/backups/uploaded_*.sql')) ?: []);
    }

    public function test_restore_requires_current_password_before_dispatching_job(): void
    {
        Bus::fake();
        $this->actingAs($this->user('super_admin'));
        $this->writeBackupFile('security_test_restore.sql');

        Livewire::test(BackupRestore::class)
            ->set('restoreExistingFilename', 'security_test_restore.sql')
            ->set('confirmPhrase', 'RESTORE')
            ->set('restorePassword', 'wrong-password')
            ->call('executeRestoreExisting')
            ->assertHasErrors(['restorePassword']);

        Bus::assertNotDispatched(RestoreDatabaseJob::class);
    }

    public function test_restore_dispatches_job_after_phrase_and_password_confirmation(): void
    {
        Bus::fake();
        $this->actingAs($this->user('super_admin'));
        $this->writeBackupFile('security_test_restore.sql');

        Livewire::test(BackupRestore::class)
            ->set('restoreExistingFilename', 'security_test_restore.sql')
            ->set('confirmPhrase', 'RESTORE')
            ->set('restorePassword', 'password')
            ->call('executeRestoreExisting')
            ->assertHasNoErrors();

        Bus::assertDispatched(RestoreDatabaseJob::class);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function uploadedFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uhlms-backup-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/sql', UPLOAD_ERR_OK, true);
    }

    private function writeBackupFile(string $name): void
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.$name, 'CREATE TABLE `demo` (`id` int);');
    }
}
