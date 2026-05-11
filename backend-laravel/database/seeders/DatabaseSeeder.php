<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Phase 2 起，认证用户表改为 admins（与老 backend 共享）；
 * Laravel 默认的 users 表已删，本 seeder 暂留为空骨架。
 * 真正建管理员账户请用 `php artisan make:filament-user` 或老 backend
 * ensureAdminAccount() 流程。
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        //
    }
}
