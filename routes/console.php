<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('dukapos:stock-alerts')->dailyAt('07:00');
