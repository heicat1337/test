-- Phase 0 schema 升级（一次性、幂等、对两种起始状态都安全）
--
-- 目标：保证 nav_sites 上以下列存在且为目标类型：
--   tags           text[]            DEFAULT '{}'::text[]    NOT NULL
--   rating         numeric(2,1)      DEFAULT 0
--   social_links   jsonb             DEFAULT '{}'::jsonb     NOT NULL
--   screenshot_url varchar(500)      DEFAULT ''
--
-- 起始状态自适应：
--   A) 列缺失（fresh DB） → 直接 ADD 到目标类型
--   B) 列已存在为 varchar/text（老 backend ensureNavSchema 加过的 CSV/JSON 字符串）
--      → ALTER 类型并把数据 cast 过去
--   C) 列已经是目标类型 → 跳过
--
-- 双跑前置：执行此脚本「之前」必须先把 backend/api/v1/index.php 与
--          backend/render_seo.php 里的 SELECT 加 cast（详见 README.md），
--          脚本「之后」必须把 backend/admin/nav-sites.php 的 INSERT/UPDATE
--          加 cast。否则旧 backend 写入会报：
--          column "tags" is of type text[] but expression is of type character varying。
--
-- 执行：
--   docker compose exec -T postgres psql -U "$DB_USER" -d "$DB_NAME" \
--     < migrations/2026_05_10_000001_phase0_upgrade_nav_sites_pg_native.sql

BEGIN;

-- ===== tags : (missing | varchar | text) -> text[] =====
DO $$
DECLARE
    v_type text;
BEGIN
    SELECT data_type INTO v_type
    FROM information_schema.columns
    WHERE table_name = 'nav_sites' AND column_name = 'tags';

    IF v_type IS NULL THEN
        ALTER TABLE nav_sites
            ADD COLUMN tags text[] NOT NULL DEFAULT '{}'::text[];

    ELSIF v_type IN ('character varying', 'text') THEN
        ALTER TABLE nav_sites ALTER COLUMN tags DROP DEFAULT;

        ALTER TABLE nav_sites
            ALTER COLUMN tags TYPE text[]
            USING (
                CASE
                    WHEN tags IS NULL OR btrim(tags::text) = '' THEN '{}'::text[]
                    ELSE (
                        SELECT COALESCE(array_agg(btrim(t)), '{}'::text[])
                        FROM unnest(string_to_array(tags::text, ',')) AS t
                        WHERE btrim(t) <> ''
                    )
                END
            );

        ALTER TABLE nav_sites ALTER COLUMN tags SET DEFAULT '{}'::text[];
        ALTER TABLE nav_sites ALTER COLUMN tags SET NOT NULL;

    ELSIF v_type = 'ARRAY' THEN
        -- 已经是数组，幂等跳过
        NULL;
    ELSE
        RAISE EXCEPTION 'unexpected tags column type: %', v_type;
    END IF;
END $$;

-- ===== social_links : (missing | text | varchar) -> jsonb =====
DO $$
DECLARE
    v_type text;
BEGIN
    SELECT data_type INTO v_type
    FROM information_schema.columns
    WHERE table_name = 'nav_sites' AND column_name = 'social_links';

    IF v_type IS NULL THEN
        ALTER TABLE nav_sites
            ADD COLUMN social_links jsonb NOT NULL DEFAULT '{}'::jsonb;

    ELSIF v_type IN ('text', 'character varying') THEN
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP DEFAULT;

        -- 把空串 / NULL / 非法 JSON 先归一成 '{}'，再 cast
        UPDATE nav_sites
        SET social_links = '{}'
        WHERE social_links IS NULL
           OR btrim(social_links::text) = ''
           OR NOT (social_links::text ~ '^\s*[\{\[]');

        ALTER TABLE nav_sites
            ALTER COLUMN social_links TYPE jsonb
            USING (social_links::jsonb);

        ALTER TABLE nav_sites ALTER COLUMN social_links SET DEFAULT '{}'::jsonb;
        ALTER TABLE nav_sites ALTER COLUMN social_links SET NOT NULL;

    ELSIF v_type = 'jsonb' THEN
        NULL;
    ELSE
        RAISE EXCEPTION 'unexpected social_links column type: %', v_type;
    END IF;
END $$;

-- ===== rating : ensure exists as numeric(2,1) DEFAULT 0 =====
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'nav_sites' AND column_name = 'rating'
    ) THEN
        ALTER TABLE nav_sites ADD COLUMN rating numeric(2,1) DEFAULT 0;
    END IF;
END $$;

-- ===== screenshot_url : ensure exists as varchar(500) DEFAULT '' =====
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'nav_sites' AND column_name = 'screenshot_url'
    ) THEN
        ALTER TABLE nav_sites ADD COLUMN screenshot_url varchar(500) DEFAULT '';
    END IF;
END $$;

-- ===== GIN 索引 =====
CREATE INDEX IF NOT EXISTS idx_nav_sites_tags_gin
    ON nav_sites USING gin (tags);

CREATE INDEX IF NOT EXISTS idx_nav_sites_social_gin
    ON nav_sites USING gin (social_links jsonb_path_ops);

-- ===== 验证 =====
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name = 'nav_sites'
  AND column_name IN ('tags', 'rating', 'social_links', 'screenshot_url', 'is_recommended')
ORDER BY ordinal_position;

COMMIT;
