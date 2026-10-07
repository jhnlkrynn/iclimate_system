<?php

namespace App\Http\Controllers;

use App\Jobs\RunDatabaseMaintenance;
use App\Models\BackupVerification;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseRestoreService;
use App\Services\SystemAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: Backups (IT). The backup history (files in the backup folder and the daily
 * verification), Back up now, Download, and Restore (typed confirmation and the IT password; a safety backup is made
 * first). Backing up and restoring run in the queue (RunDatabaseMaintenance), never inside the page.
 */
class BackupController extends Controller
{
    public function index(DatabaseBackupService $backups): View
    {
        $directory = $backups->directory();
        $files = collect(is_dir($directory) ? scandir($directory) : [])
            ->filter(fn (string $name) => preg_match(DatabaseBackupService::FILE_PATTERN, $name))
            ->map(fn (string $name) => ['name' => $name, 'size' => filesize($directory.DIRECTORY_SEPARATOR.$name), 'time' => filemtime($directory.DIRECTORY_SEPARATOR.$name)])
            ->sortByDesc('time')->values();

        return view('admin.backups', [
            'files' => $files,
            'status' => rescue(fn () => $backups->status(), ['state' => 'unavailable', 'last' => null, 'size' => null, 'count' => 0], false),
            'verifications' => rescue(fn () => BackupVerification::query()->latest('verified_at')->limit(10)->get(), collect(), false),
            'last' => Cache::get(RunDatabaseMaintenance::RESULT_KEY),
            'retentionDays' => $backups->retentionDays(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RunDatabaseMaintenance::dispatch('backup', null, $request->user()->getKey());
        SystemAuditLogger::record('Requested Database Backup', $request);

        return redirect()->route('admin.backups.index')->with('success', 'Backup started. It takes a minute or two; refresh this page to see it.');
    }

    public function download(Request $request, string $file, DatabaseRestoreService $restore): BinaryFileResponse
    {
        $path = $restore->pathFor($file) ?? abort(404);
        SystemAuditLogger::record('Downloaded Database Backup', $request, ['file' => $file]);

        return response()->download($path, $file);
    }

    public function restore(Request $request, string $file, DatabaseRestoreService $restore): RedirectResponse
    {
        abort_if($restore->pathFor($file) === null, 404);
        $request->validate([
            'confirm' => ['required', 'in:RESTORE'],
            'password' => ['required', 'string'],
        ], ['confirm.in' => 'Type RESTORE in capital letters to confirm.']);
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            return back()->withErrors(['password' => 'That is not your password.']);
        }

        RunDatabaseMaintenance::dispatch('restore', $file, $request->user()->getKey());
        SystemAuditLogger::record('Requested Database Restore', $request, ['file' => $file]);

        return redirect()->route('admin.backups.index')->with('success', 'Restore started: a safety backup is made first, then '.$file.' is restored. Everyone may be signed out.');
    }
}
