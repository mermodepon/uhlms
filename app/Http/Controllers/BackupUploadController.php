<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BackupUploadController extends Controller
{
    public function upload(Request $request)
    {
        // Auth check - super_admin only
        if (! auth()->check() || ! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $request->validate([
            'backup_file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('backup_file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'sql') {
            Log::warning('Backup upload rejected: invalid extension', [
                'original' => $originalName,
                'user' => auth()->user()?->name,
                'ip' => $request->ip(),
            ]);

            return back()->with('upload_error', 'Only .sql files are accepted.');
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false || ! $this->isAcceptableSqlBackup($contents)) {
            Log::warning('Backup upload rejected: invalid content', [
                'original' => $originalName,
                'user' => auth()->user()?->name,
                'ip' => $request->ip(),
            ]);

            return back()->with('upload_error', 'File does not look like a supported SQL backup.');
        }

        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // Save with the original name (or timestamped to avoid conflicts)
        $filename = 'uploaded_'.now()->format('Y-m-d_His').'.sql';
        $file->move($backupDir, $filename);

        Log::info('Backup file uploaded', [
            'filename' => $filename,
            'original' => $originalName,
            'user' => auth()->user()->name,
            'ip' => $request->ip(),
        ]);

        return back()->with('upload_success', "File uploaded as {$filename}. You can now restore it from the backup list.");
    }

    private function isAcceptableSqlBackup(string $contents): bool
    {
        $sample = substr($contents, 0, 1048576);

        if (preg_match('/<\?php|<script|__halt_compiler|<html|<!doctype\s+html/i', $sample)) {
            return false;
        }

        // The restore parser is intentionally simple and does not support stored routine dumps.
        if (preg_match('/^\s*DELIMITER\s+/im', $sample)) {
            return false;
        }

        return (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|ALTER\s+TABLE|DROP\s+TABLE|SET\s+FOREIGN_KEY_CHECKS)\b/i', $sample);
    }
}
