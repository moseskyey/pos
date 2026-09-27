<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('dukapos:stock-alerts')->dailyAt('07:00');
Schedule::command('dukapos:recurring-expenses')->dailyAt('06:00');
Schedule::command('dukapos:debt-alerts')->weeklyOn(1, '08:00');
Schedule::command('dukapos:reconcile-payments')->everyMinute()->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('01:30');
Schedule::command('backup:monitor')->dailyAt('09:00');
