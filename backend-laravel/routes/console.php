<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Phase 7：Laravel 接管调度
|--------------------------------------------------------------------------
| Laravel Schedule 是主调度入口，老 backend bin/cron.php 不再需要跑。
|
| 生产部署：
|   * cron 配 `* * * * * cd /app && php artisan schedule:run`
|   * docker-compose 起一个 long-running 容器跑 `php artisan geoflow:worker`
|
| 双跑期间：如果老 scheduler 容器（profile=scheduler）还在运行，
| 它会和这里的 Schedule 同时入队同一个 task。下线老 scheduler 后 Phase 9 通切。
*/

// 主调度：每分钟扫活跃任务入队（与老 bin/cron.php 行为对齐）
Schedule::command('geoflow:cron-tick')
    ->everyMinute()
    ->withoutOverlapping();

// 健康检查：恢复卡死 job + 清过期 worker_heartbeats
Schedule::command('geoflow:health-check')
    ->everyTenMinutes();

// 自动发布：把审核通过的 draft 改 published
Schedule::command('geoflow:auto-publish')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// RSS 抓取：每 2 小时一轮
Schedule::command('geoflow:rss-fetch')
    ->cron('0 */2 * * *')
    ->withoutOverlapping();

// 数据库清理 + VACUUM
Schedule::command('geoflow:db-maintenance cleanup')->dailyAt('03:00');
Schedule::command('geoflow:db-maintenance vacuum')->dailyAt('03:30');
