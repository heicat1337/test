<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1：双跑共享缓存版本号。
 *
 * 老 backend（backend/）和新 Laravel（backend-laravel/）通过这张表
 * 同步导航数据版本。任意一方写入时把版本号 +1，对方读路径感知后失效。
 *
 * 单行设计（id=1）+ CHECK 约束保证只能有一条数据。
 */
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE IF NOT EXISTS nav_cache_version (
    id          INT PRIMARY KEY DEFAULT 1,
    version     BIGINT NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT nav_cache_version_singleton CHECK (id = 1)
);

INSERT INTO nav_cache_version (id, version)
VALUES (1, 1)
ON CONFLICT (id) DO NOTHING;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS nav_cache_version');
    }
};
