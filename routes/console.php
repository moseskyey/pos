<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Per-business jobs run once for every business ("tenants:run").
Schedule::command('tenants:run dukapos:reconcile-payments')->everyMinute()->withoutOverlapping();
Schedule::command('tenants:run dukapos:recurring-expenses --active')->dailyAt('06:00');
Schedule::command('tenants:run dukapos:stock-alerts --active')->dailyAt('07:00');
Schedule::command('tenants:run dukapos:debt-alerts --active')->weeklyOn(1, '08:00');
Schedule::command('tenants:run backup:clean')->dailyAt('01:00');
Schedule::command('tenants:run "backup:run --only-db --disable-notifications"')->dailyAt('01:30')->withoutOverlapping();

// Platform jobs (central database).
Schedule::command('billing:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('billing:reminders')->dailyAt('09:15');
Schedule::command('platform:backup')->dailyAt('03:00');
Schedule::command('platform:backup --monitor')->dailyAt('09:00');

Artisan::command('platform:backup {--monitor : Only check that recent backups exist}', function () {
    // The central database (businesses, plans, billing), kept apart from business backups.
    config(['backup.backup.name' => 'dukapos-platform', 'backup.backup.source.databases' => ['central']]);
    foreach (array_keys(config('backup.monitor_backups')) as $i) {
        config(["backup.monitor_backups.{$i}.name" => 'dukapos-platform']);
    }
    if ($this->option('monitor')) {
        return $this->call('backup:monitor');
    }
    $this->call('backup:clean');

    return $this->call('backup:run', ['--only-db' => true]);
})->purpose('Back up the central platform database');
