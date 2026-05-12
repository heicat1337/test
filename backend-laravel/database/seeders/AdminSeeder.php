<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 自动创建默认管理员（仅在 admins 表为空时执行）。
 *
 * 通过环境变量覆盖：
 *   AUTO_SEED_ADMIN_EMAIL    (默认: admin@geoflow.local)
 *   AUTO_SEED_ADMIN_PASSWORD (默认: admin123)
 *   AUTO_SEED_ADMIN_NAME     (默认: Admin)
 *
 * 每次运行都安全——已存在 admin 时不做任何操作。
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Admin::count() > 0) {
            return; // 已有管理员，跳过
        }

        $email    = env('AUTO_SEED_ADMIN_EMAIL', 'admin@geoflow.local');
        $password = env('AUTO_SEED_ADMIN_PASSWORD', 'admin123');
        $name     = env('AUTO_SEED_ADMIN_NAME', 'Admin');

        Admin::create([
            'username'     => $name,
            'display_name' => $name,
            'email'        => $email,
            'password'     => Hash::make($password),
            'role'         => 'super_admin',
            'status'       => 'active',
        ]);

        $this->command?->info("默认管理员已创建: {$email}");
    }
}
