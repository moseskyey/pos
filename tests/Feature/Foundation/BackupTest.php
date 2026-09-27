<?php

use App\Jobs\RunBackup;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['backup.backup.destination.disks' => ['local']]);
});

it('lists, downloads and deletes backups for owners', function () {
    actingAsRole('owner');
    Storage::disk('local')->put('dukapos/2026-09-01-01-30-00.zip', 'zip');

    $this->get(route('backups.index'))->assertOk()->assertSee('2026-09-01-01-30-00.zip');
    $this->get(route('backups.download', '2026-09-01-01-30-00.zip'))->assertOk();
    $this->delete(route('backups.destroy', '2026-09-01-01-30-00.zip'))->assertRedirect();
    Storage::disk('local')->assertMissing('dukapos/2026-09-01-01-30-00.zip');
});

it('queues a backup run', function () {
    Queue::fake();
    $user = actingAsRole('owner');
    $this->post(route('backups.store'), ['type' => 'db'])->assertRedirect();
    Queue::assertPushed(RunBackup::class, fn ($job) => $job->userId === $user->id && $job->onlyDb);
});

it('rejects path traversal and unknown files', function () {
    actingAsRole('owner');
    $this->get(route('backups.download', '..%2F.env'))->assertNotFound();
    $this->get(route('backups.download', 'missing.zip'))->assertNotFound();
});

it('forbids backups without permission', function () {
    actingAsRole('manager');
    $this->get(route('backups.index'))->assertForbidden();
});
