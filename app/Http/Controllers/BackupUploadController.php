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
            return back()->with('upload_error', 'Only .sql files are accepted.');
        }

        // Validate content is actual SQL, not PHP/script injection
        $contents = file_get_contents($file->getRealPath());
        if (preg_match('/<\?php|<script|__halt_compiler/i', $contents)) {
            return back()->with('upload_error', 'File contains disallowed content.');
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
        ]);

        return back()->with('upload_success', "File uploaded as {$filename}. You can now restore it from the backup list.");
    }
}
