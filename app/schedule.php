<?php
// Scheduled tasks: run `php bongo schedule:run` every minute from cron (or Windows Task Scheduler).
// See them with `php bongo schedule:list`.

use App\Jobs\SendLowStockReport;

/** @var System\Scheduling\Schedule $schedule */

$schedule->job(new SendLowStockReport(threshold: 10))->dailyAt('08:00')->name('Email the low stock report');

$schedule->command('view:clear')->weekly()->name('Clear compiled views');
