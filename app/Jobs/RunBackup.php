<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs spatie/laravel-backup on demand from the Backups page and tells the
 * requesting user how it went.
 */
class RunBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $userId, public bool $onlyDb = true) {}

    public function handle(): void
    {
        $exit = Artisan::call('backup:run', array_filter([
            '--only-db' => $this->onlyDb,
            '--disable-notifications' => true,
        ]));

        $user = User::find($this->userId);
        activity('backups')->causedBy($user)->withProperties(['only_db' => $this->onlyDb, 'exit' => $exit])
            ->log($exit === 0 ? 'Backup created' : 'Backup failed');

        $user?->notify($exit === 0
            ? new SystemAlert(__('Backup complete'), __('A new backup is ready to download.'), route('backups.index'), 'bi-cloud-check', 'success')
            : new SystemAlert(__('Backup failed'), trim(Artisan::output()) ?: __('Check the logs for details.'), route('backups.index'), 'bi-cloud-slash', 'danger', true));
    }
}
