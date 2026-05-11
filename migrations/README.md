# Side-by-side 迁移：手工 SQL 迁移脚本

本目录放「Laravel scaffold 完成前」需要先跑的 schema 升级。
Laravel 起来后会把这些转成 `database/migrations/*.php` 接管。

## 当前脚本

| 文件 | 作用 |
|---|---|
| `2026_05_10_000001_phase0_upgrade_nav_sites_pg_native.sql` | 把 `nav_sites.tags` 升成 `text[]`、`social_links` 升成 `jsonb`，加 GIN 索引 |
| `2026_05_10_000001_phase0_upgrade_nav_sites_pg_native_down.sql` | 回滚（数据保留） |

## ⚠️ 执行顺序（必读）

升级 schema **会把字段类型从字符串改成 PG 原生数组/jsonb**，旧 PHP backend 的写入语句（CSV/JSON 字符串绑定参数）会立即报类型错。
所以执行顺序是：

1. **先**给老 backend 打读写兼容补丁（见下「Legacy 兼容补丁清单」）
2. 重启老 backend 容器
3. **再**跑此 SQL 升级
4. 验证 `/api/v1/nav/categories` 与 `/api/v1/nav/sites/{id}` 返回正常

回滚顺序反过来：先跑 `_down.sql`，再撤补丁。

## 跑迁移命令

```bash
# 在 test/ 下
docker compose exec -T postgres psql \
  -U "${DB_USER:-geo_user}" \
  -d "${DB_NAME:-geo_system}" \
  < migrations/2026_05_10_000001_phase0_upgrade_nav_sites_pg_native.sql
```

执行成功最后一行会列出 5 个字段的当前类型，应该看到：

```
 tags           | ARRAY            | NO       | '{}'::text[]
 rating         | numeric          | YES      | 0
 social_links   | jsonb            | NO       | '{}'::jsonb
 screenshot_url | character varying| YES      | ''::character varying
 is_recommended | boolean          | YES      | false
```

## Legacy 兼容补丁清单

升级类型后，旧 backend 必须在两处做最小改动。**为避免双向漂移，强烈建议读侧用 SQL cast 回字符串**——这样 PHP 端零改动。

### A. 读：4 处 SELECT 把 tags/social_links cast 回字符串（推荐）

只在 `SELECT ... s.tags, s.social_links ...` 里改成：

```sql
array_to_string(s.tags, ',') AS tags,
s.social_links::text AS social_links
```

需要改的 SELECT 位置：
- `backend/api/v1/index.php:87-98`（categories JOIN 查询）
- `backend/api/v1/index.php:140-141`（sites 列表）
- `backend/api/v1/index.php:159-167`（site 详情）
- `backend/api/v1/index.php:188-194`（recommended）
- `backend/render_seo.php:69-79`（categories 渲染）
- `backend/render_seo.php:122-130`（site 详情渲染）
- `backend/admin/nav-sites.php` 列表查询（如有展示 tags 的，按需）

改完后 `nav_serialize_site_row()`、`seo_serialize_site_row()` 现有的 explode/json_decode 逻辑**完全不用动**。

### B. 写：2 处 INSERT/UPDATE 必须加 cast

`backend/admin/nav-sites.php`:

**Line ~106-109（INSERT）**：

```php
// 原：
INSERT INTO nav_sites (..., tags, rating, social_links, screenshot_url, ...)
VALUES (..., ?, ?, ?, ?, ...)

// 改成（仅参数占位符部分）：
INSERT INTO nav_sites (..., tags, rating, social_links, screenshot_url, ...)
VALUES (..., string_to_array(?, ','), ?, ?::jsonb, ?, ...)
```

`$tags` 仍然传 CSV 字符串，`$social_links_json` 仍然传 JSON 字符串——**PHP 业务代码不改**，只 SQL 加 cast。

**Line ~118-126（UPDATE）**：

```php
// 原：tags = ?, ..., social_links = ?, ...
// 改：tags = string_to_array(?, ','), ..., social_links = ?::jsonb, ...
```

注意空字符串 `$tags = ''` 会被 `string_to_array(',')` 变成 `{''}` 单元素数组——PHP 那边在调用前 trim 过滤了空 token，传过来已经是 `'a,b,c'` 或 `''`。空串 cast 出来是 `{''}`，要先在 PHP 里把空串改成 `null`，让 SQL 用 `COALESCE(string_to_array(?, ','), '{}')`：

```php
$tags_param = $tags === '' ? null : $tags;
// SQL: COALESCE(string_to_array(?, ','), '{}'::text[])
```

同理 `$social_links_json === ''` → 改用 `'{}'::jsonb`，绑参时把空串换成 `'{}'`。

### C. database_admin.php 里的旧 ALTER（不用改）

`includes/database_admin.php:806-816` 的 `ALTER TABLE ... ADD COLUMN tags VARCHAR(500)` 等是幂等判断 `db_column_exists` 后再加，列已升级成 `text[]` / `jsonb` 后判断为 true，不会重复 ADD。**保持原样即可**。

---

## Phase 1 起来后

Laravel scaffold 完成后，把本目录 SQL 内容移植成 `backend-laravel/database/migrations/2026_05_10_000001_upgrade_nav_sites_pg_native.php`：

```php
public function up(): void
{
    DB::statement(file_get_contents(database_path('migrations/raw/2026_05_10_000001_phase0_upgrade_nav_sites_pg_native.sql')));
}
```

或者拆成 Schema builder + 几条 raw `DB::statement()`，看哪种更顺手。
