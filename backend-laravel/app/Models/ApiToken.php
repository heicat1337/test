<?php

namespace App\Models;

use App\Casts\PgJsonbObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * API Token（与老 backend includes/api_token_service.php 共用同一张表）。
 *
 * - token 明文只在创建时返回一次给 admin（不持久化），数据库里只存 SHA256 hash
 * - scopes 是 jsonb 数组：['catalog:read', 'tasks:write', ...]
 * - status: active / revoked
 */
class ApiToken extends Model
{
    use HasFactory;

    protected $table = 'api_tokens';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'token_hash',
        'scopes',
        'status',
        'created_by_admin_id',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes'              => 'array',          // jsonb 数组（不强制对象）
            'created_by_admin_id' => 'int',
            'last_used_at'        => 'datetime',
            'expires_at'          => 'datetime',
            'created_at'          => 'datetime',
            'updated_at'          => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /**
     * 生成新 token：返回 [明文, model]。明文只此一次可见。
     */
    public static function issue(
        string $name,
        array $scopes,
        ?int $createdByAdminId = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $plaintext = 'xua_' . Str::random(48);
        $token = static::create([
            'name'                => $name,
            'token_hash'          => hash('sha256', $plaintext),
            'scopes'              => array_values(array_unique($scopes)),
            'status'              => 'active',
            'created_by_admin_id' => $createdByAdminId,
            'expires_at'          => $expiresAt,
        ]);
        return [$plaintext, $token];
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }
}
