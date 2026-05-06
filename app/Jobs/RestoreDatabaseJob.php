<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreDatabaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum execution time for the job (1 hour).
     */
    public int $timeout = 3600;

    public function __construct(
        protected string $filepath,
        protected string $filename,
        protected string $initiatedBy,
    ) {}

    public function handle(): void
    {
        $statusFile = storage_path('app/backups/_restore_status.txt');
        $runningFile = storage_path('app/backups/_restore_running.txt');

        try {
            // Create a safety snapshot before wiping data
            $this->createPreRestoreBackup();

            $sql = file_get_contents($this->filepath);

            if ($sql === false) {
                throw new \RuntimeException("Could not read backup file: {$this->filepath}");
            }

            $statements = $this->parseSqlStatements($sql);

            DB::unprepared('SET FOREIGN_KEY_CHECKS=0');

            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }

            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');

            Log::info('Database restore completed successfully', [
                'filename' => $this->filename,
                'user'      => $this->initiatedBy,
                'statements' => count($statements),
            ]);

            file_put_contents($statusFile, 'SUCCESS');
        } catch (\Throwable $e) {
            Log::error('Database restore failed', [
                'filename' => $this->filename,
                'error'    => $e->getMessage(),
            ]);

            try {
                DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
                // Silently ignore — connection may be in a bad state after restore failure
            }

            file_put_contents($statusFile, 'FAILED');
        } finally {
            if (file_exists($runningFile)) {
                unlink($runningFile);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Pre-restore backup
    // -------------------------------------------------------------------------

    private function createPreRestoreBackup(): void
    {
        try {
            $filepath = storage_path('app/backups/pre_restore_'.now()->format('Y-m-d_His').'.sql');
            $this->exportDatabaseToFile($filepath);
            Log::info('Pre-restore backup created', ['file' => basename($filepath)]);
        } catch (\Throwable $e) {
            Log::warning('Pre-restore backup failed, proceeding anyway', ['error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // PDO-based export (no external binaries required)
    // -------------------------------------------------------------------------

    private function exportDatabaseToFile(string $filepath): void
    {
        $pdo = DB::getPdo();
        $fp  = fopen($filepath, 'w');

        if (! $fp) {
            throw new \RuntimeException("Cannot open file for writing: {$filepath}");
        }

        try {
            fwrite($fp, "-- UHLMS Database Backup\n");
            fwrite($fp, '-- Generated: '.now()->toDateTimeString()."\n\n");
            fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            /** @var string[] $tables */
            $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $quoted = '`'.str_replace('`', '``', $table).'`';

                $createRow = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(\PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'];

                fwrite($fp, "DROP TABLE IF EXISTS {$quoted};\n");
                fwrite($fp, $createSql.";\n\n");

                $stmt = $pdo->query("SELECT * FROM {$quoted}");
                $cols = null;
                $rows = [];

                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if ($cols === null) {
                        $cols = array_map(
                            fn ($c) => '`'.str_replace('`', '``', $c).'`',
                            array_keys($row)
                        );
                    }

                    $rows[] = '('.implode(', ', array_map(
                        fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    )).')';

                    // Flush every 500 rows to keep memory bounded
                    if (count($rows) >= 500) {
                        fwrite($fp, 'INSERT INTO '.$quoted.' ('.implode(', ', $cols).") VALUES\n".implode(",\n", $rows).";\n");
                        $rows = [];
                    }
                }

                if (! empty($rows)) {
                    fwrite($fp, 'INSERT INTO '.$quoted.' ('.implode(', ', $cols).") VALUES\n".implode(",\n", $rows).";\n");
                }

                fwrite($fp, "\n");
            }

            fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fp);
        }
    }

    // -------------------------------------------------------------------------
    // SQL parser — handles string literals and comments correctly
    // -------------------------------------------------------------------------

    /**
     * Split a SQL dump into individual statements, respecting:
     *   - single-quoted, double-quoted, and backtick-quoted strings
     *   - backslash escapes inside strings
     *   - single-line (--) and block comments
     *
     * @return string[]
     */
    private function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inString   = false;
        $stringChar = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            if ($inString) {
                // Backslash escape — consume next char verbatim
                if ($char === '\\') {
                    $current .= $char.($sql[$i + 1] ?? '');
                    $i++;
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                }
                $current .= $char;
                continue;
            }

            // Start of a quoted string
            if ($char === "'" || $char === '"' || $char === '`') {
                $inString   = true;
                $stringChar = $char;
                $current   .= $char;
                continue;
            }

            // Single-line comment: skip to end of line
            if ($char === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // Block comment: skip to closing */
            if ($char === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && ! ($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2; // skip */
                continue;
            }

            // Statement terminator
            if ($char === ';') {
                $stmt = trim($current);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Trailing statement without semicolon
        $stmt = trim($current);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }

        return $statements;
    }
}
