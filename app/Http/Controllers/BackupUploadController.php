<?php

namespace App\Http\Controllers;

use App\Services\EncryptedBackupService;
use App\Support\AdminMfa;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class BackupUploadController extends Controller
{
    public function upload(
        Request $request,
        EncryptedBackupService $backups,
        SecurityAudit $audit,
    ): RedirectResponse {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(AdminMfa::isEnabled($request->user()) && AdminMfa::isRecent(), 403, 'Recent MFA confirmation is required.');

        $request->validate([
            'backup_file' => ['required', 'file', 'max:51200', 'extensions:uhlmsbak'],
        ]);

        $upload = $request->file('backup_file');
        $original = basename($upload->getClientOriginalName());
        try {
            $backups->validate($upload->getRealPath());
        } catch (Throwable $exception) {
            $audit->alert(
                'backup_upload_failed',
                'Backup upload rejected',
                'An unauthenticated, damaged, or incompatible backup upload was rejected.',
                ['actor_id' => $request->user()->id, 'filename' => $original, 'reason' => class_basename($exception)],
                'danger',
                true,
            );

            return back()->with('upload_error', 'The backup is damaged, unauthenticated, or was encrypted with a different key.');
        }

        $filename = 'uploaded_'.now()->format('Y-m-d_His_u').'.uhlmsbak';
        $destination = $backups->path($filename);
        if (! $upload->move($backups->directory(), $filename)) {
            throw new \RuntimeException('The uploaded backup could not be stored.');
        }

        try {
            $audit->alert(
                'backup_uploaded',
                'Database backup uploaded',
                'An authenticated encrypted backup was uploaded.',
                ['actor_id' => $request->user()->id, 'filename' => $filename],
                'warning',
                true,
            );
        } catch (Throwable $exception) {
            @unlink($destination);
            throw $exception;
        }

        return back()->with('upload_success', "File uploaded as {$filename}. It can now be restored from the backup list.");
    }
}
