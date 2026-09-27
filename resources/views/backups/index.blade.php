<x-layouts.app :title="__('Backups')" :breadcrumbs="[__('Settings'), __('Backups')]">
    <x-page-header :title="__('Backups')" :subtitle="__('Database and uploaded files, stored on the :disk disk. Automatic database backups run nightly at 01:30.', ['disk' => $disk])">
        <form method="POST" action="{{ route('backups.store') }}" class="d-flex gap-2">@csrf
            <button name="type" value="full" class="btn btn-outline-secondary"><i class="bi bi-archive"></i> {{ __('Full backup') }}</button>
            <button name="type" value="db" class="btn btn-primary"><i class="bi bi-database-down"></i> {{ __('Back up database') }}</button>
        </form>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Backups kept')" :value="$backups->count()" icon="bi-archive" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Latest backup')" :value="$backups->first() ? $backups->first()['date']->diffForHumans() : __('Never')" icon="bi-clock-history" :color="$backups->first() && $backups->first()['date']->gt(now()->subDay()) ? 'success' : 'warning'" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Total size')" :value="\Illuminate\Support\Number::fileSize($backups->sum('size'), 1)" icon="bi-hdd" color="info" /></div>
        <div class="col-sm-6 col-xl-3"><x-stat-card :label="__('Retention')" :value="__(':n days', ['n' => config('backup.cleanup.default_strategy.keep_all_backups_for_days')])" icon="bi-calendar-check" color="secondary" /></div>
    </div>

    <div class="card">
        @if ($backups->isEmpty())
            <x-empty-state icon="bi-cloud-arrow-down" :title="__('No backups yet')" :message="__('Create your first backup now. Keep a copy off-site (download it or configure an S3 disk).')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover table-stack mb-0">
                    <thead><tr><th>{{ __('File') }}</th><th>{{ __('Created') }}</th><th class="text-end">{{ __('Size') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($backups as $backup)
                        <tr>
                            <td data-label="{{ __('File') }}" class="font-monospace small"><i class="bi bi-file-earmark-zip text-primary me-1"></i>{{ $backup['name'] }}</td>
                            <td data-label="{{ __('Created') }}">{{ format_date($backup['date'], true) }} <span class="text-body-secondary small">· {{ $backup['date']->diffForHumans() }}</span></td>
                            <td data-label="{{ __('Size') }}" class="text-end">{{ \Illuminate\Support\Number::fileSize($backup['size'], 1) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('backups.download', $backup['name']) }}" class="btn btn-sm btn-soft-primary"><i class="bi bi-download"></i> {{ __('Download') }}</a>
                                <form method="POST" action="{{ route('backups.destroy', $backup['name']) }}" class="d-inline" data-confirm="{{ __('Delete this backup permanently?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-soft-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
