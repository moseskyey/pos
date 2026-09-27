<?php

namespace App\Http\Controllers;

use App\Jobs\RunBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('backups.index', [
            'backups' => $this->backups(),
            'disk' => $this->disk(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', 'in:db,full']]);
        RunBackup::dispatch($request->user()->id, $data['type'] === 'db');

        return back()->with('success', __('Backup started. You will be notified when it is ready.'));
    }

    public function download(string $file): StreamedResponse
    {
        $path = $this->path($file);
        activity('backups')->causedBy(auth()->user())->withProperties(['file' => $file])->log('Backup downloaded');

        return Storage::disk($this->disk())->download($path);
    }

    public function destroy(string $file): RedirectResponse
    {
        Storage::disk($this->disk())->delete($this->path($file));
        activity('backups')->causedBy(auth()->user())->withProperties(['file' => $file])->log('Backup deleted');

        return back()->with('success', __('Backup deleted.'));
    }

    private function disk(): string
    {
        return config('backup.backup.destination.disks')[0] ?? 'local';
    }

    private function folder(): string
    {
        return config('backup.backup.name');
    }

    /**
     * Resolve a file name to a path inside the backup folder, rejecting traversal.
     */
    private function path(string $file): string
    {
        abort_unless(preg_match('/^[\w.-]+\.zip$/', $file) === 1, 404);
        $path = $this->folder().'/'.$file;
        abort_unless(Storage::disk($this->disk())->exists($path), 404);

        return $path;
    }

    private function backups(): Collection
    {
        $disk = Storage::disk($this->disk());

        return collect($disk->files($this->folder()))
            ->filter(fn ($p) => str_ends_with($p, '.zip'))
            ->map(fn ($p) => [
                'name' => basename($p),
                'size' => $disk->size($p),
                'date' => Carbon::createFromTimestamp($disk->lastModified($p)),
            ])
            ->sortByDesc('date')
            ->values();
    }
}
