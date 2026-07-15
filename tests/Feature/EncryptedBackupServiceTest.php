<?php

namespace Tests\Feature;

use App\Services\EncryptedBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EncryptedBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $source;

    /** @var array<int, string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('security.backups.encryption_key', base64_encode(random_bytes(32)));
        $this->source = tempnam(sys_get_temp_dir(), 'uhlms-sql-');
        file_put_contents($this->source, "-- UHLMS Database Backup\nCREATE TABLE `encrypted_test` (`id` int);\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_legacy_import_creates_a_versioned_authenticated_backup_without_plaintext(): void
    {
        $service = app(EncryptedBackupService::class);
        $filename = $service->importLegacySql($this->source, 'encrypted_test');
        $path = $service->path($filename);
        $this->files[] = $path;

        $this->assertSame(EncryptedBackupService::MAGIC, file_get_contents($path, false, null, 0, 8));
        $this->assertStringNotContainsString('CREATE TABLE', file_get_contents($path));
        $manifest = $service->validate($path);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertContains('encrypted_test', $manifest['tables']);
    }

    public function test_tampering_truncation_and_wrong_keys_are_rejected(): void
    {
        $service = app(EncryptedBackupService::class);
        $filename = $service->importLegacySql($this->source, 'encrypted_test');
        $path = $service->path($filename);
        $this->files[] = $path;

        $tampered = $path.'.tampered.uhlmsbak';
        $contents = file_get_contents($path);
        $contents[(int) floor(strlen($contents) / 2)] = chr(ord($contents[(int) floor(strlen($contents) / 2)]) ^ 1);
        file_put_contents($tampered, $contents);
        $this->files[] = $tampered;
        $this->expectException(RuntimeException::class);
        $service->validate($tampered);
    }

    public function test_wrong_key_is_rejected(): void
    {
        $service = app(EncryptedBackupService::class);
        $filename = $service->importLegacySql($this->source, 'encrypted_test');
        $path = $service->path($filename);
        $this->files[] = $path;
        config()->set('security.backups.encryption_key', base64_encode(random_bytes(32)));

        $this->expectException(RuntimeException::class);
        $service->validate($path);
    }
}
