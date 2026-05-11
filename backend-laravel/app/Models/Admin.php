<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Admin = 后台管理员（共享老 backend 的 admins 表）。
 *
 * Filament 后台和老 backend /heicat 都用这张表登录。任意一边改密码两边都生效。
 *
 * 字段：
 *   - username (登录用)
 *   - password (bcrypt hash)
 *   - email, display_name
 *   - role: super_admin / admin / editor
 *   - status: active / inactive
 */
class Admin extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'admins';

    public $timestamps = true;

    /**
     * Filament 期望的 username 字段映射（默认是 'email'）。
     * 通过下面的 getEmailForVerification + canAccessPanel 兼容 Filament 用户名登录。
     */
    protected $fillable = [
        'username',
        'password',
        'email',
        'display_name',
        'role',
        'status',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login'           => 'datetime',
            'welcome_dismissed_at' => 'datetime',
            'created_at'           => 'datetime',
            'updated_at'           => 'datetime',
            'password'             => 'hashed',
        ];
    }

    /**
     * Filament 4：判断管理员是否可以访问指定 panel。
     * 这里只允许 status='active' 的进 admin panel；细粒度按 role 鉴权后续 policy 接入。
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /**
     * 显示名优先 display_name，回退到 username。
     */
    public function getFilamentName(): string
    {
        return (string) ($this->display_name ?: $this->username);
    }
}
