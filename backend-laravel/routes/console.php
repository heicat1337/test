<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Phase 4：调度（双跑兼容）
|--------------------------------------------------------------------------
|
| 默认情况下，老 backend 的 `web3-scheduler` 容器（docker-compose profile
| `scheduler`）持续跑 bin/cron.php / worker.php。Laravel 端不重复消费——
| 否则同一个 task 会被两边各入队一次。
|
| 如果你**关闭**了老 scheduler 容器，想让 Laravel 来兜底调度，
| 把下面的 Schedule 注释解开：
|
|   Schedule::command('geoflow:cron --script=cron')->everyMinute()->withoutOverlapping();
|   Schedule::command('geoflow:cron --script=auto_publish')->everyFiveMinutes();
|   Schedule::command('geoflow:cron --script=db_maintenance')->dailyAt('03:00');
|   Schedule::command('geoflow:cron --script=health_check_cron')->everyTenMinutes();
|   Schedule::command('geoflow:cron --script=rss_fetcher')->hourly();
|
| 然后启动 Laravel 的 cron 入口：
|   `* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1`
*/
