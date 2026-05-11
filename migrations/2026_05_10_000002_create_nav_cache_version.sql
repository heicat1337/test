-- Phase 1：双跑共享版本号
--
-- 老 backend（PHP file cache）和新 Laravel（database cache）共用同一张 PG 表
-- 来追踪导航数据版本。任意一方写入 nav_categories / nav_sites 时把这个数 +1，
-- 对方下次读时发现版本变化即刷新。
--
-- 单行设计（id=1）+ CHECK 约束保证只能有一条数据。

BEGIN;

CREATE TABLE IF NOT EXISTS nav_cache_version (
    id          INT PRIMARY KEY DEFAULT 1,
    version     BIGINT NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT nav_cache_version_singleton CHECK (id = 1)
);

INSERT INTO nav_cache_version (id, version)
VALUES (1, 1)
ON CONFLICT (id) DO NOTHING;

COMMIT;
