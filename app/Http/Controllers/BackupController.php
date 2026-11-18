<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(): View
    {
        $backups = Backup::with('creator')->latest()->paginate(15);

        return view('backups.index', compact('backups'));
    }

    public function store(BackupService $service): RedirectResponse
    {
        try {
            $backup = $service->create('manual', Auth::id());

            return back()->with('success', __('Backup created: :filename (:size).', [
                'filename' => $backup->filename,
                'size' => $backup->readable_size,
            ]));
        } catch (Throwable $e) {
            return back()->with('error', __('Backup failed: :message', ['message' => $e->getMessage()]));
        }
    }

    public function download(Backup $backup, BackupService $service): BinaryFileResponse|RedirectResponse
    {
        $path = $service->path($backup);
        if (! is_file($path)) {
            return back()->with('error', __('Backup file is missing on disk.'));
        }

        return response()->download($path, $backup->filename);
    }

    public function restore(Backup $backup, BackupService $service): RedirectResponse
    {
        try {
            $service->restore($backup);

            return back()->with('success', __('Database restored from :filename.', ['filename' => $backup->filename]));
        } catch (Throwable $e) {
            return back()->with('error', __('Restore failed: :message', ['message' => $e->getMessage()]));
        }
    }

    public function destroy(Backup $backup, BackupService $service): RedirectResponse
    {
        $service->delete($backup);

        return back()->with('success', __('Backup deleted.'));
    }
}
