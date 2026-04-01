<x-filament-panels::page>
    <div class="space-y-6" @if($restoreInProgress) wire:poll.2s="pollRestoreStatus" @endif>

        {{-- Create Backup Section --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-950 dark:text-white">Database Backups</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Create and manage database backups. Only the last 10 backups are kept.
                    </p>
                </div>
                <button
                    wire:click="createBackup"
                    wire:loading.attr="disabled"
                    wire:target="createBackup"
                    @if($restoreInProgress) disabled @endif
                    class="fi-btn fi-btn-size-md relative inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold
                           bg-primary-600 text-white shadow-sm hover:bg-primary-500
                           dark:bg-primary-500 dark:hover:bg-primary-400
                           disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <span wire:loading.remove wire:target="createBackup">
                        <x-heroicon-m-plus class="w-5 h-5 inline -mt-0.5" />
                        Create Backup
                    </span>
                    <span wire:loading wire:target="createBackup">
                        <svg class="animate-spin h-5 w-5 inline -mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Creating...
                    </span>
                </button>
            </div>

            {{-- Backup List --}}
            @if(count($this->backups) > 0)
                <div class="overflow-x-auto border rounded-lg dark:border-gray-700">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Filename</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Size</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Created</th>
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-400 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->backups as $backup)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-gray-100">
                                        {{ $backup['filename'] }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ $this->formatSize($backup['size']) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {{ \Carbon\Carbon::createFromTimestamp($backup['created_at'])->format('M d, Y h:i A') }}
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1">
                                        <button
                                            wire:click="downloadBackup('{{ $backup['filename'] }}')"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium
                                                   text-primary-600 bg-primary-50 hover:bg-primary-100
                                                   dark:text-primary-400 dark:bg-primary-950 dark:hover:bg-primary-900 transition"
                                            title="Download"
                                        >
                                            <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                                            Download
                                        </button>
                                        <button
                                            wire:click="restoreFromExisting('{{ $backup['filename'] }}')"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium
                                                   text-amber-600 bg-amber-50 hover:bg-amber-100
                                                   dark:text-amber-400 dark:bg-amber-950 dark:hover:bg-amber-900 transition"
                                            title="Restore from this backup"
                                        >
                                            <x-heroicon-m-arrow-path class="w-4 h-4" />
                                            Restore
                                        </button>
                                        <button
                                            wire:click="deleteBackup('{{ $backup['filename'] }}')"
                                            wire:confirm="Are you sure you want to delete this backup?"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium
                                                   transition"
                                            style="color: #dc2626; background-color: #fef2f2;"
                                            onmouseover="this.style.backgroundColor='#fee2e2'"
                                            onmouseout="this.style.backgroundColor='#fef2f2'"
                                            title="Delete"
                                        >
                                            <x-heroicon-m-trash class="w-4 h-4" />
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-6 text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-circle-stack class="w-10 h-10 mx-auto mb-2 opacity-40" />
                    <p class="text-sm">No backups yet. Click "Create Backup" to get started.</p>
                </div>
            @endif
        </div>

        {{-- Upload Backup File Section --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h3 class="text-lg font-medium text-gray-950 dark:text-white mb-1">Upload Backup File</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Upload a <code class="text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">.sql</code> backup file. It will appear in the backup list above, where you can restore it. Maximum file size: 50 MB.
            </p>

            @if(session('upload_success'))
                <div class="mb-4 p-3 rounded-lg" style="background-color: #f0fdf4; border: 1px solid #bbf7d0;">
                    <p class="text-sm font-medium" style="color: #166534;">{{ session('upload_success') }}</p>
                </div>
            @endif
            @if(session('upload_error'))
                <div class="mb-4 p-3 rounded-lg" style="background-color: #fef2f2; border: 1px solid #fecaca;">
                    <p class="text-sm font-medium" style="color: #991b1b;">{{ session('upload_error') }}</p>
                </div>
            @endif

            <form action="{{ route('backup.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-end gap-4">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Backup File (.sql)</label>
                    <input
                        type="file"
                        name="backup_file"
                        accept=".sql"
                        required
                        class="block w-full text-sm text-gray-500 dark:text-gray-400
                               file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                               file:text-sm file:font-semibold
                               file:bg-primary-50 file:text-primary-700
                               dark:file:bg-primary-950 dark:file:text-primary-400
                               hover:file:bg-primary-100 dark:hover:file:bg-primary-900
                               transition cursor-pointer"
                    />
                    @error('backup_file') <span class="text-sm mt-1" style="color: #dc2626;">{{ $message }}</span> @enderror
                </div>
                <button
                    type="submit"
                    class="fi-btn rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition"
                    style="background-color: #d97706; color: #fff;"
                    onmouseover="this.style.backgroundColor='#b45309'"
                    onmouseout="this.style.backgroundColor='#d97706'"
                >
                    <x-heroicon-m-arrow-up-tray class="w-5 h-5 inline -mt-0.5" />
                    Upload
                </button>
            </form>
        </div>

        {{-- Restore Confirmation Modal --}}
        @if($showRestoreModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelRestore">
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 w-full max-w-md mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center" style="background-color: #fee2e2;">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6" style="color: #dc2626;" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Confirm Database Restore</h3>
                        </div>
                    </div>

                    <div class="mb-4 p-3 rounded-lg" style="background-color: #fef2f2; border: 1px solid #fecaca;">
                        <p class="text-sm font-medium" style="color: #991b1b;">
                            Warning: This will overwrite the entire database with the backup data. This action cannot be undone.
                        </p>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        Restoring from: <strong>{{ $restoreExistingFilename }}</strong>
                    </p>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type <strong>RESTORE</strong> to confirm:
                        </label>
                        <input
                            type="text"
                            wire:model.defer="confirmPhrase"
                            placeholder="RESTORE"
                            autocomplete="off"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600
                                   bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                   shadow-sm text-sm px-3 py-2
                                   focus:ring-2 focus:ring-primary-600 focus:border-primary-600"
                        />
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            wire:click="cancelRestore"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300
                                   bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600
                                   hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            wire:click="executeRestoreExisting"
                            wire:loading.attr="disabled"
                            style="background-color: #dc2626; color: #fff;"
                            class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm
                                   disabled:opacity-50 disabled:cursor-not-allowed transition"
                            onmouseover="this.style.backgroundColor='#b91c1c'"
                            onmouseout="this.style.backgroundColor='#dc2626'"
                        >
                            <span wire:loading.remove wire:target="executeRestoreExisting">
                                Restore Database
                            </span>
                            <span wire:loading wire:target="executeRestoreExisting">
                                <svg class="animate-spin h-4 w-4 inline -mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Restoring...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Restore In Progress Overlay --}}
        @if($restoreInProgress)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 w-full max-w-sm mx-4 p-8 text-center">
                    <svg class="animate-spin h-10 w-10 mx-auto mb-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-950 dark:text-white mb-2">Restoring Database...</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Please do not close this page. A pre-restore backup was created automatically.
                    </p>
                </div>
            </div>
        @endif
    </div>

</x-filament-panels::page>
