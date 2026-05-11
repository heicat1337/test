<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 0 schema 升级：把 nav_sites 的 tags/social_links 改成 PG 原生类型。
 *
 * 本迁移与 test/migrations/2026_05_10_000001_phase0_upgrade_nav_sites_pg_native.sql 等价。
 * 完全幂等：列缺失则 ADD（目标类型），已存在为 varchar/text 则 ALTER 转换，
 *          已是目标类型则跳过。所以即使 raw SQL 已经跑过，artisan migrate 也安全。
 */
return new class extends Migration {
    public function up(): void
    {
        // tags : (missing | varchar | text) -> text[]
        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    v_type text;
BEGIN
    SELECT data_type INTO v_type FROM information_schema.columns
    WHERE table_name = 'nav_sites' AND column_name = 'tags';

    IF v_type IS NULL THEN
        ALTER TABLE nav_sites ADD COLUMN tags text[] NOT NULL DEFAULT '{}'::text[];
    ELSIF v_type IN ('character varying', 'text') THEN
        ALTER TABLE nav_sites ALTER COLUMN tags DROP DEFAULT;
        ALTER TABLE nav_sites ALTER COLUMN tags TYPE text[] USING (
            CASE WHEN tags IS NULL OR btrim(tags::text) = '' THEN '{}'::text[]
                 ELSE (
                    SELECT COALESCE(array_agg(btrim(t)), '{}'::text[])
                    FROM unnest(string_to_array(tags::text, ',')) AS t
                    WHERE btrim(t) <> ''
                 )
            END
        );
        ALTER TABLE nav_sites ALTER COLUMN tags SET DEFAULT '{}'::text[];
        ALTER TABLE nav_sites ALTER COLUMN tags SET NOT NULL;
    END IF;
END $$;
SQL);

        // social_links : (missing | text | varchar) -> jsonb
        DB::unprepared(<<<'SQL'
DO $$
DECLARE
    v_type text;
BEGIN
    SELECT data_type INTO v_type FROM information_schema.columns
    WHERE table_name = 'nav_sites' AND column_name = 'social_links';

    IF v_type IS NULL THEN
        ALTER TABLE nav_sites ADD COLUMN social_links jsonb NOT NULL DEFAULT '{}'::jsonb;
    ELSIF v_type IN ('text', 'character varying') THEN
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP DEFAULT;
        UPDATE nav_sites SET social_links = '{}'
        WHERE social_links IS NULL
           OR btrim(social_links::text) = ''
           OR NOT (social_links::text ~ '^\s*[\{\[]');
        ALTER TABLE nav_sites ALTER COLUMN social_links TYPE jsonb USING (social_links::jsonb);
        ALTER TABLE nav_sites ALTER COLUMN social_links SET DEFAULT '{}'::jsonb;
        ALTER TABLE nav_sites ALTER COLUMN social_links SET NOT NULL;
    END IF;
END $$;
SQL);

        // rating + screenshot_url : ensure exist
        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='nav_sites' AND column_name='rating') THEN
        ALTER TABLE nav_sites ADD COLUMN rating numeric(2,1) DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_name='nav_sites' AND column_name='screenshot_url') THEN
        ALTER TABLE nav_sites ADD COLUMN screenshot_url varchar(500) DEFAULT '';
    END IF;
END $$;
SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idx_nav_sites_tags_gin ON nav_sites USING gin (tags)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_nav_sites_social_gin ON nav_sites USING gin (social_links jsonb_path_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_nav_sites_tags_gin');
        DB::statement('DROP INDEX IF EXISTS idx_nav_sites_social_gin');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='nav_sites' AND column_name='tags' AND data_type='ARRAY') THEN
        ALTER TABLE nav_sites ALTER COLUMN tags DROP NOT NULL;
        ALTER TABLE nav_sites ALTER COLUMN tags DROP DEFAULT;
        ALTER TABLE nav_sites ALTER COLUMN tags TYPE varchar(500)
            USING COALESCE(array_to_string(tags, ','), '');
        ALTER TABLE nav_sites ALTER COLUMN tags SET DEFAULT '';
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_name='nav_sites' AND column_name='social_links' AND data_type='jsonb') THEN
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP NOT NULL;
        ALTER TABLE nav_sites ALTER COLUMN social_links DROP DEFAULT;
        ALTER TABLE nav_sites ALTER COLUMN social_links TYPE text USING social_links::text;
        ALTER TABLE nav_sites ALTER COLUMN social_links SET DEFAULT '';
    END IF;
END $$;
SQL);
    }
};
