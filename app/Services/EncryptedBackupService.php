<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class EncryptedBackupService
{
    public const MAGIC = 'UHLMSBK1';

    public const VERSION = 1;

    private const CHUNK_BYTES = 65536;

    public function directory(): string
    {
        $directory = (string) config('security.backups.directory', storage_path('app/backups'));

        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('The backup directory could not be created.');
        }

        if (DIRECTORY_SEPARATOR === '/') {
            @chmod($directory, 0700);
        }

        return $directory;
    }

    public function path(string $filename): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.basename($filename);
    }

    public function createDatabaseBackup(string $prefix = 'backup'): string
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('Encrypted database export requires MySQL.');
        }

        $tables = DB::getPdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $filename = $prefix.'_'.now()->format('Y-m-d_His_u').'.uhlmsbak';
        $manifest = [
            'format_version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'schema_marker' => $this->schemaMarker(),
            'tables' => array_values($tables),
            'excluded_data_tables' => array_values(config('security.backups.excluded_data_tables', [])),
        ];

        $this->encryptChunks($this->databaseDump($tables, $manifest), $this->path($filename));

        return $filename;
    }

    public function importLegacySql(string $sourcePath, string $prefix = 'legacy'): string
    {
        $handle = @fopen($sourcePath, 'rb');
        if (! $handle) {
            throw new RuntimeException('The legacy SQL file cannot be read.');
        }

        $sample = fread($handle, 4096);
        rewind($handle);
        if (! is_string($sample) || ! str_contains($sample, 'UHLMS Database Backup')) {
            fclose($handle);
            throw new RuntimeException('The file is not a recognized UHLMS SQL backup.');
        }

        $tables = $this->legacyTableNames($sourcePath);
        $manifest = [
            'format_version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'schema_marker' => 'legacy-import',
            'tables' => $tables,
            'excluded_data_tables' => [],
        ];
        $filename = $prefix.'_'.now()->format('Y-m-d_His_u').'.uhlmsbak';

        $chunks = (function () use ($handle, $manifest): Generator {
            try {
                yield $this->manifestLine($manifest);
                while (! feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_BYTES);
                    if ($chunk === false) {
                        throw new RuntimeException('The legacy SQL file could not be streamed.');
                    }
                    if ($chunk !== '') {
                        yield $chunk;
                    }
                }
            } finally {
                fclose($handle);
            }
        })();

        $this->encryptChunks($chunks, $this->path($filename));
        $this->validate($this->path($filename));

        return $filename;
    }

    /** @return array<string, mixed> */
    public function validate(string $path): array
    {
        $prefix = '';
        foreach ($this->decryptChunks($path) as $chunk) {
            if (! str_contains($prefix, "\n")) {
                $prefix .= $chunk;
                if (strlen($prefix) > self::CHUNK_BYTES) {
                    throw new RuntimeException('The encrypted backup manifest is invalid.');
                }
            }
        }

        $line = strstr($prefix, "\n", true);
        if (! is_string($line) || ! str_starts_with($line, 'UHLMS-MANIFEST ')) {
            throw new RuntimeException('The encrypted backup manifest is missing.');
        }

        $json = base64_decode(substr($line, strlen('UHLMS-MANIFEST ')), true);
        $manifest = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($manifest)
            || ($manifest['format_version'] ?? null) !== self::VERSION
            || ! is_array($manifest['tables'] ?? null)
            || ! is_string($manifest['created_at'] ?? null)
            || ! is_string($manifest['schema_marker'] ?? null)) {
            throw new RuntimeException('The encrypted backup manifest is malformed.');
        }

        foreach ($manifest['tables'] as $table) {
            if (! is_string($table) || preg_match('/\A[A-Za-z0-9_]+\z/', $table) !== 1) {
                throw new RuntimeException('The encrypted backup contains an invalid table manifest.');
            }
        }

        return $manifest;
    }

    public function restore(string $path): int
    {
        // First pass authenticates every frame and the FINAL tag before SQL runs.
        $manifest = $this->validate($path);
        $allowedTables = array_fill_keys($manifest['tables'], true);
        $statementCount = 0;

        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->sqlStatements($this->decryptChunks($path)) as $statement) {
                if (str_starts_with($statement, 'UHLMS-MANIFEST ')) {
                    $statement = trim((string) strstr($statement, "\n"));
                    if ($statement === '') {
                        continue;
                    }
                }

                $this->assertAllowedStatement($statement, $allowedTables);
                DB::unprepared($statement);
                $statementCount++;
            }
        } finally {
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }

        return $statementCount;
    }

    /** @return array<int, string> */
    public function prune(string $prefix, int $keep, int $days): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.$prefix.'_*.uhlmsbak') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $deleted = [];
        $cutoff = now()->subDays($days)->timestamp;

        foreach ($files as $index => $file) {
            if ($index >= $keep || filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $deleted[] = basename($file);
                }
            }
        }

        return $deleted;
    }

    /** @param array<int, string> $tables @param array<string, mixed> $manifest */
    private function databaseDump(array $tables, array $manifest): Generator
    {
        $pdo = DB::getPdo();
        $excluded = array_fill_keys(config('security.backups.excluded_data_tables', []), true);

        yield $this->manifestLine($manifest);
        yield "-- UHLMS Database Backup\nSET FOREIGN_KEY_CHECKS=0;\n";

        foreach ($tables as $table) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $createRow = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(\PDO::FETCH_ASSOC);
            $createSql = $createRow['Create Table'] ?? null;
            if (! is_string($createSql)) {
                throw new RuntimeException("Could not export table {$table}.");
            }

            yield "DROP TABLE IF EXISTS {$quoted};\n{$createSql};\n";
            if (isset($excluded[$table])) {
                continue;
            }

            $statement = $pdo->query("SELECT * FROM {$quoted}");
            $columns = null;
            $rows = [];
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $columns ??= array_map(fn (string $column): string => '`'.str_replace('`', '``', $column).'`', array_keys($row));
                $rows[] = '('.implode(', ', array_map(
                    fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row),
                )).')';

                if (count($rows) === 500) {
                    yield 'INSERT INTO '.$quoted.' ('.implode(', ', $columns).") VALUES\n".implode(",\n", $rows).";\n";
                    $rows = [];
                }
            }

            if ($rows !== []) {
                yield 'INSERT INTO '.$quoted.' ('.implode(', ', $columns).") VALUES\n".implode(",\n", $rows).";\n";
            }
        }

        yield "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    /** @param iterable<string> $chunks */
    private function encryptChunks(iterable $chunks, string $path): void
    {
        $temporary = $path.'.part';
        $handle = @fopen($temporary, 'xb');
        if (! $handle) {
            throw new RuntimeException('The encrypted backup file could not be created.');
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
            $this->writeAll($handle, self::MAGIC.$header);
            foreach ($chunks as $chunk) {
                foreach (str_split($chunk, self::CHUNK_BYTES) as $piece) {
                    if ($piece === '') {
                        continue;
                    }
                    $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $piece);
                    $this->writeFrame($handle, $cipher);
                }
            }
            $final = sodium_crypto_secretstream_xchacha20poly1305_push(
                $state,
                '',
                '',
                SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL,
            );
            $this->writeFrame($handle, $final);
            fflush($handle);
            fclose($handle);
            $handle = null;

            if (! @rename($temporary, $path)) {
                throw new RuntimeException('The encrypted backup could not be finalized.');
            }
            if (DIRECTORY_SEPARATOR === '/') {
                @chmod($path, 0600);
            }
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($temporary);
            throw $exception;
        }
    }

    /** @return Generator<int, string> */
    private function decryptChunks(string $path): Generator
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('The encrypted backup cannot be read.');
        }

        try {
            $prefix = $this->readExact($handle, strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (substr($prefix, 0, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('The backup format is not recognized.');
            }
            $header = substr($prefix, strlen(self::MAGIC));
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key());
            $sawFinal = false;

            while (! feof($handle)) {
                $lengthBytes = fread($handle, 4);
                if ($lengthBytes === '') {
                    break;
                }
                if ($lengthBytes === false || strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('The encrypted backup is truncated.');
                }
                $length = unpack('Nlength', $lengthBytes)['length'];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > self::CHUNK_BYTES + 1024) {
                    throw new RuntimeException('The encrypted backup frame is invalid.');
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $this->readExact($handle, $length));
                if ($result === false) {
                    throw new RuntimeException('The backup authentication check failed.');
                }
                [$plain, $tag] = $result;
                if ($sawFinal) {
                    throw new RuntimeException('The encrypted backup contains trailing data.');
                }
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $sawFinal = true;
                    if (! feof($handle) && fread($handle, 1) !== '') {
                        throw new RuntimeException('The encrypted backup contains trailing data.');
                    }
                } elseif ($plain !== '') {
                    yield $plain;
                }
            }

            if (! $sawFinal) {
                throw new RuntimeException('The encrypted backup is truncated.');
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param iterable<string> $chunks @return Generator<int, string> */
    private function sqlStatements(iterable $chunks): Generator
    {
        $current = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $previous = '';

        foreach ($chunks as $chunk) {
            $length = strlen($chunk);
            for ($i = 0; $i < $length; $i++) {
                $character = $chunk[$i];
                $next = $chunk[$i + 1] ?? '';

                if ($lineComment) {
                    if ($character === "\n") {
                        $lineComment = false;
                        $current .= "\n";
                    }

                    continue;
                }
                if ($blockComment) {
                    if ($previous === '*' && $character === '/') {
                        $blockComment = false;
                    }
                    $previous = $character;

                    continue;
                }
                if ($quote !== null) {
                    $current .= $character;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($character === '\\') {
                        $escaped = true;
                    } elseif ($character === $quote) {
                        $quote = null;
                    }

                    continue;
                }
                if ($character === '-' && $next === '-') {
                    $lineComment = true;
                    $i++;

                    continue;
                }
                if ($character === '/' && $next === '*') {
                    $blockComment = true;
                    $previous = '';
                    $i++;

                    continue;
                }
                if (in_array($character, ["'", '"', '`'], true)) {
                    $quote = $character;
                    $current .= $character;

                    continue;
                }
                if ($character === ';') {
                    if (trim($current) !== '') {
                        yield trim($current);
                    }
                    $current = '';

                    continue;
                }
                $current .= $character;
            }
        }

        if (trim($current) !== '') {
            yield trim($current);
        }
    }

    /** @param array<string, bool> $allowedTables */
    private function assertAllowedStatement(string $statement, array $allowedTables): void
    {
        if (preg_match('/\ASET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]\z/i', $statement)) {
            return;
        }

        if (! preg_match('/\A(?:DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE|INSERT\s+INTO)\s+`([^`]+)`/i', $statement, $match)
            || ! isset($allowedTables[$match[1]])) {
            throw new RuntimeException('The backup contains a statement not produced by the UHLMS exporter.');
        }
    }

    private function key(): string
    {
        $encoded = trim((string) config('security.backups.encryption_key'));
        if (str_starts_with($encoded, 'base64:')) {
            $encoded = substr($encoded, 7);
        }
        $key = base64_decode($encoded, true);
        if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY must be a separate base64-encoded 32-byte key.');
        }

        return $key;
    }

    /** @param array<string, mixed> $manifest */
    private function manifestLine(array $manifest): string
    {
        return 'UHLMS-MANIFEST '.base64_encode(json_encode($manifest, JSON_THROW_ON_ERROR))."\n";
    }

    private function schemaMarker(): string
    {
        try {
            return 'migration-batch:'.(string) DB::table('migrations')->max('batch');
        } catch (Throwable) {
            return 'migration-batch:unknown';
        }
    }

    /** @return array<int, string> */
    private function legacyTableNames(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('The legacy SQL file cannot be read.');
        }
        preg_match_all('/(?:CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE\s+IF\s+EXISTS)\s+`([A-Za-z0-9_]+)`/i', $contents, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @param resource $handle */
    private function writeFrame($handle, string $cipher): void
    {
        $this->writeAll($handle, pack('N', strlen($cipher)).$cipher);
    }

    /** @param resource $handle */
    private function writeAll($handle, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($handle, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException('The encrypted backup could not be written.');
            }
            $bytes = substr($bytes, $written);
        }
    }

    /** @param resource $handle */
    private function readExact($handle, int $length): string
    {
        $bytes = '';
        while (strlen($bytes) < $length && ! feof($handle)) {
            $chunk = fread($handle, $length - strlen($bytes));
            if ($chunk === false) {
                throw new RuntimeException('The encrypted backup could not be read.');
            }
            $bytes .= $chunk;
        }
        if (strlen($bytes) !== $length) {
            throw new RuntimeException('The encrypted backup is truncated.');
        }

        return $bytes;
    }
}
