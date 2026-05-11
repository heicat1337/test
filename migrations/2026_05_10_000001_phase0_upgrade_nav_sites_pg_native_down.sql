-- 回滚：把 nav_sites.tags / social_links 退回到字符串形态。
-- 数据保留：text[] -> CSV，jsonb -> 紧凑 JSON 字符串。

BEGIN;

DROP INDEX IF EXISTS idx_nav_sites_tags_gin;
DROP INDEX IF EXISTS idx_nav_sites_social_gin;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'nav_sites' AND column_name = 'tags' AND data_type = 'ARRAY'
    ) THEN
        ALTER TABLE nav_sites ALTER COLUMN tags DROP NOT NULL;
        ALTER TABLE nav_sites ALTER COLUMN tags DROP DEFAULT;
        ALTER TABLE nav_sites
            ALTER COLUMN tags TYPE varchar(500)
            USING COALESCE(array_to_string(tags, ','), '');
        ALTER TABLE nav_sites ALTER COLUMN tags SET DEFAULT '';
    END IF;

    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'nav_sites' AND column_name = 'social_links' AND data_type = 'jsonb'
    ) THEN
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP NOT NULL;
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP DEFAULT;
        ALTER TABLE nav_sites
            ALTER COLUMN social_links TYPE text
            USING social_links::text;
        ALTER TABLE nav_sites ALTER COLUMN social_links SET DEFAULT '';
    END IF;
END $$;

COMMIT;
