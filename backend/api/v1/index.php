<?php
/**
 * API v1 单入口
 */

define('FEISHU_TREASURE', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database_admin.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/api_response.php';
require_once __DIR__ . '/../../includes/api_request.php';
require_once __DIR__ . '/../../includes/api_token_service.php';
require_once __DIR__ . '/../../includes/api_auth.php';
require_once __DIR__ . '/../../includes/api_admin_auth_service.php';
require_once __DIR__ . '/../../includes/catalog_service.php';
require_once __DIR__ . '/../../includes/task_lifecycle_service.php';
require_once __DIR__ . '/../../includes/article_service.php';
require_once __DIR__ . '/../../includes/nav_cache.php';

$request = new ApiRequest();
$requestId = $request->getRequestId();
$statusCode = 200;
$responsePayload = [];
$routeKey = null;
$shouldStoreIdempotency = false;

try {
    $tokenService = new ApiTokenService($db);
    $auth = new ApiAuth($tokenService);
    $authContext = null;

    $catalogService = new CatalogService($db);
    $taskService = new TaskLifecycleService($db);
    $articleService = new ArticleService($db);
    $adminAuthService = new ApiAdminAuthService($db, $tokenService);

    $segments = $request->getSegments();
    $method = $request->getMethod();
    $body = $request->getBody();

    if ($segments === []) {
        throw new ApiException('not_found', '接口不存在', 404);
    }

    $isAuthLoginRoute = $segments[0] === 'auth' && $method === 'POST' && count($segments) === 2 && $segments[1] === 'login';
    $isNavRoute = $segments[0] === 'nav';
    $isPublicArticleRoute = $segments[0] === 'articles' && $method === 'GET' && (
        count($segments) === 1 ||
        (count($segments) === 3 && $segments[1] === 'by-slug')
    );

    if ($isPublicArticleRoute) {
        if (count($segments) === 1) {
            $responsePayload = api_build_success_payload($articleService->listArticles(
                $request->getQueryInt('page', 1),
                $request->getQueryInt('per_page', 20),
                [
                    'status' => $request->getQueryString('status', 'published'),
                ]
            ), $requestId);
            $statusCode = 200;
        } elseif (count($segments) === 3 && $segments[1] === 'by-slug') {
            $slug = $segments[2];
            $stmt = $db->prepare('SELECT * FROM articles WHERE slug = ? AND status = ? LIMIT 1');
            $stmt->execute([$slug, 'published']);
            $article = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$article) {
                throw new ApiException('not_found', '文章不存在', 404);
            }
            $db->prepare('UPDATE articles SET view_count = view_count + 1 WHERE id = ?')->execute([$article['id']]);
            $article['view_count'] = intval($article['view_count'] ?? 0) + 1;
            // 记录浏览日志（用于今日统计）
            try {
                $ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $db->prepare('INSERT INTO view_logs (article_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, NOW())')->execute([$article['id'], substr($ip, 0, 45), substr($ua, 0, 500)]);
            } catch (Throwable $e) {}
            $responsePayload = api_build_success_payload($article, $requestId);
            $statusCode = 200;
        }
    } elseif ($isNavRoute && $method === 'GET') {
        if (count($segments) === 2 && $segments[1] === 'categories') {
            $cats = NavCache::get('categories', 60);
            if ($cats === null) {
                // 单查询 + PHP 端 group by，消除原本 1 + N 的 SQL 往返
                // Phase 0 schema 升级后：tags=text[]、social_links=jsonb；
                // 这里 cast 成 csv/json 字符串，让 nav_serialize_site_row() 的 explode/json_decode 不动。
                $stmt = $db->query('
                    SELECT
                        c.id AS cat_id, c.name AS cat_name, c.slug AS cat_slug, c.icon AS cat_icon, c.sort_order AS cat_sort,
                        s.id AS site_id, s.name AS site_name, s.url AS site_url,
                        s.description AS site_desc, s.icon AS site_icon,
                        s.sort_order AS site_sort, s.is_recommended AS site_rec,
                        array_to_string(s.tags, \',\') AS site_tags, s.rating AS site_rating,
                        s.social_links::text AS site_social, s.screenshot_url AS site_shot
                    FROM nav_categories c
                    LEFT JOIN nav_sites s ON s.category_id = c.id
                    ORDER BY c.sort_order ASC, c.id ASC, s.sort_order ASC, s.id ASC
                ');
                $byCat = [];
                foreach ($stmt as $row) {
                    $cid = (int)$row['cat_id'];
                    if (!isset($byCat[$cid])) {
                        $byCat[$cid] = [
                            'id' => $cid,
                            'name' => $row['cat_name'],
                            'slug' => $row['cat_slug'] ?: ('cat-' . $cid),
                            'icon' => $row['cat_icon'],
                            'sort_order' => (int)$row['cat_sort'],
                            'sites' => [],
                        ];
                    }
                    if ($row['site_id'] !== null) {
                        $byCat[$cid]['sites'][] = nav_serialize_site_row([
                            'id' => $row['site_id'],
                            'name' => $row['site_name'],
                            'url' => $row['site_url'],
                            'description' => $row['site_desc'],
                            'icon' => $row['site_icon'],
                            'sort_order' => $row['site_sort'],
                            'category_id' => $cid,
                            'is_recommended' => $row['site_rec'],
                            'tags' => $row['site_tags'],
                            'rating' => $row['site_rating'],
                            'social_links' => $row['site_social'],
                            'screenshot_url' => $row['site_shot'],
                        ]);
                    }
                }
                $cats = array_values($byCat);
                NavCache::set('categories', $cats);
            }
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $responsePayload = api_build_success_payload($cats, $requestId);
            $statusCode = 200;
        } elseif (count($segments) === 2 && $segments[1] === 'sites') {
            $categoryId = $request->getQueryInt('category_id', 0);
            $cacheKey = 'sites_' . ($categoryId > 0 ? $categoryId : 'all');
            $rows = NavCache::get($cacheKey, 60);
            if ($rows === null) {
                $sql = 'SELECT id, name, url, description, icon, sort_order, category_id, is_recommended,
                               array_to_string(tags, \',\') AS tags, rating,
                               social_links::text AS social_links, screenshot_url FROM nav_sites';
                if ($categoryId > 0) {
                    $stmt = $db->prepare($sql . ' WHERE category_id = ? ORDER BY sort_order ASC, id ASC');
                    $stmt->execute([$categoryId]);
                } else {
                    $stmt = $db->query($sql . ' ORDER BY sort_order ASC, id ASC');
                }
                $rows = array_map('nav_serialize_site_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
                NavCache::set($cacheKey, $rows);
            }
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $responsePayload = api_build_success_payload($rows, $requestId);
            $statusCode = 200;
        } elseif (count($segments) === 3 && $segments[1] === 'sites' && ctype_digit($segments[2])) {
            $siteId = (int) $segments[2];
            $cacheKey = 'site_' . $siteId;
            $site = NavCache::get($cacheKey, 60);
            if ($site === null) {
                $stmt = $db->prepare('
                    SELECT s.id, s.name, s.url, s.description, s.icon, s.sort_order, s.category_id,
                           s.is_recommended,
                           array_to_string(s.tags, \',\') AS tags, s.rating,
                           s.social_links::text AS social_links, s.screenshot_url,
                           c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
                    FROM nav_sites s
                    LEFT JOIN nav_categories c ON c.id = s.category_id
                    WHERE s.id = ?
                    LIMIT 1
                ');
                $stmt->execute([$siteId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new ApiException('not_found', '项目不存在', 404);
                }
                $site = nav_serialize_site_row($row);
                $site['category'] = $row['category_id'] ? [
                    'id' => (int) $row['category_id'],
                    'name' => $row['category_name'],
                    'slug' => $row['category_slug'] ?: ('cat-' . (int) $row['category_id']),
                    'icon' => $row['category_icon'],
                ] : null;
                NavCache::set($cacheKey, $site);
            }
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $responsePayload = api_build_success_payload($site, $requestId);
            $statusCode = 200;
        } elseif (count($segments) === 2 && $segments[1] === 'recommended') {
            $rows = NavCache::get('recommended', 60);
            if ($rows === null) {
                $stmt = $db->query('
                    SELECT id, name, url, description, icon, sort_order, category_id,
                           array_to_string(tags, \',\') AS tags, rating,
                           social_links::text AS social_links, screenshot_url
                    FROM nav_sites
                    WHERE is_recommended = TRUE
                    ORDER BY sort_order ASC, id ASC
                ');
                $rows = array_map(function ($r) {
                    $r['is_recommended'] = true;
                    return nav_serialize_site_row($r);
                }, $stmt->fetchAll(PDO::FETCH_ASSOC));
                NavCache::set('recommended', $rows);
            }
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            $responsePayload = api_build_success_payload($rows, $requestId);
            $statusCode = 200;
        } else {
            throw new ApiException('not_found', '接口不存在', 404);
        }
    } elseif ($isAuthLoginRoute) {
        $responsePayload = api_build_success_payload($adminAuthService->login(
            trim((string) ($body['username'] ?? '')),
            (string) ($body['password'] ?? ''),
            api_detect_client_ip(),
            trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        ), $requestId);
        $statusCode = 200;
    } else if (!$isPublicArticleRoute) {
        $authContext = $auth->authenticate($request);
    }

    if ($isAuthLoginRoute || $isNavRoute || $isPublicArticleRoute) {
        // no-op, handled above
    } elseif ($segments[0] === 'catalog' && $method === 'GET' && count($segments) === 1) {
        $auth->requireScope($authContext, 'catalog:read');
        $responsePayload = api_build_success_payload($catalogService->getCatalog(), $requestId);
        $statusCode = 200;
    } elseif ($segments[0] === 'tasks') {
        if ($method === 'GET' && count($segments) === 1) {
            $auth->requireScope($authContext, 'tasks:read');
            $data = $taskService->listTasks(
                $request->getQueryInt('page', 1),
                $request->getQueryInt('per_page', 20),
                [
                    'status' => $request->getQueryString('status'),
                    'search' => $request->getQueryString('search')
                ]
            );
            $responsePayload = api_build_success_payload($data, $requestId);
            $statusCode = 200;
        } elseif ($method === 'POST' && count($segments) === 1) {
            $auth->requireScope($authContext, 'tasks:write');
            $routeKey = 'POST /tasks';
            $shouldStoreIdempotency = true;
            if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                api_emit_payload($cached['payload'], $cached['status']);
            }
            $responsePayload = api_build_success_payload($taskService->createTask($body), $requestId);
            $statusCode = 201;
        } elseif (count($segments) >= 2 && ctype_digit($segments[1])) {
            $taskId = (int) $segments[1];

            if ($method === 'GET' && count($segments) === 2) {
                $auth->requireScope($authContext, 'tasks:read');
                $responsePayload = api_build_success_payload($taskService->getTask($taskId), $requestId);
                $statusCode = 200;
            } elseif ($method === 'PATCH' && count($segments) === 2) {
                $auth->requireScope($authContext, 'tasks:write');
                $routeKey = 'PATCH /tasks/{id}';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($taskService->updateTask($taskId, $body), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'start') {
                $auth->requireScope($authContext, 'tasks:write');
                $routeKey = 'POST /tasks/{id}/start';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $enqueueNow = !empty($body['enqueue_now']);
                $responsePayload = api_build_success_payload($taskService->startTask($taskId, $enqueueNow), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'stop') {
                $auth->requireScope($authContext, 'tasks:write');
                $routeKey = 'POST /tasks/{id}/stop';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($taskService->stopTask($taskId), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'enqueue') {
                $auth->requireScope($authContext, 'tasks:write');
                $routeKey = 'POST /tasks/{id}/enqueue';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $jobType = trim((string) ($body['job_type'] ?? 'generate_article'));
                $payload = $body;
                unset($payload['job_type']);
                $responsePayload = api_build_success_payload($taskService->enqueueTask($taskId, $jobType, $payload), $requestId);
                $statusCode = 201;
            } elseif ($method === 'GET' && count($segments) === 3 && $segments[2] === 'jobs') {
                $auth->requireScope($authContext, 'tasks:read');
                $responsePayload = api_build_success_payload($taskService->listTaskJobs(
                    $taskId,
                    $request->getQueryString('status', ''),
                    $request->getQueryInt('limit', 20)
                ), $requestId);
                $statusCode = 200;
            } else {
                throw new ApiException('not_found', '接口不存在', 404);
            }
        } else {
            throw new ApiException('not_found', '接口不存在', 404);
        }
    } elseif ($segments[0] === 'jobs' && $method === 'GET' && count($segments) === 2 && ctype_digit($segments[1])) {
        $auth->requireScope($authContext, 'jobs:read');
        $responsePayload = api_build_success_payload($taskService->getJob((int) $segments[1]), $requestId);
        $statusCode = 200;
    } elseif ($segments[0] === 'articles') {
        if ($method === 'GET' && count($segments) === 1) {
            $auth->requireScope($authContext, 'articles:read');
            $responsePayload = api_build_success_payload($articleService->listArticles(
                $request->getQueryInt('page', 1),
                $request->getQueryInt('per_page', 20),
                [
                    'task_id' => $request->getQueryInt('task_id', 0),
                    'status' => $request->getQueryString('status'),
                    'review_status' => $request->getQueryString('review_status'),
                    'author_id' => $request->getQueryInt('author_id', 0),
                    'search' => $request->getQueryString('search')
                ]
            ), $requestId);
            $statusCode = 200;
        } elseif ($method === 'POST' && count($segments) === 1) {
            $auth->requireScope($authContext, 'articles:write');
            $routeKey = 'POST /articles';
            $shouldStoreIdempotency = true;
            if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                api_emit_payload($cached['payload'], $cached['status']);
            }
            $responsePayload = api_build_success_payload($articleService->createArticle($body), $requestId);
            $statusCode = 201;
        } elseif (count($segments) >= 2 && ctype_digit($segments[1])) {
            $articleId = (int) $segments[1];

            if ($method === 'GET' && count($segments) === 2) {
                $auth->requireScope($authContext, 'articles:read');
                $responsePayload = api_build_success_payload($articleService->getArticle($articleId), $requestId);
                $statusCode = 200;
            } elseif ($method === 'PATCH' && count($segments) === 2) {
                $auth->requireScope($authContext, 'articles:write');
                $routeKey = 'PATCH /articles/{id}';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($articleService->updateArticle($articleId, $body), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'review') {
                $auth->requireScope($authContext, 'articles:publish');
                $routeKey = 'POST /articles/{id}/review';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($articleService->reviewArticle(
                    $articleId,
                    trim((string) ($body['review_status'] ?? '')),
                    trim((string) ($body['review_note'] ?? '')),
                    $authContext->auditAdminId
                ), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'publish') {
                $auth->requireScope($authContext, 'articles:publish');
                $routeKey = 'POST /articles/{id}/publish';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($articleService->publishArticle($articleId), $requestId);
                $statusCode = 200;
            } elseif ($method === 'POST' && count($segments) === 3 && $segments[2] === 'trash') {
                $auth->requireScope($authContext, 'articles:write');
                $routeKey = 'POST /articles/{id}/trash';
                $shouldStoreIdempotency = true;
                if ($cached = api_handle_idempotency_if_needed($db, $request, $routeKey)) {
                    api_emit_payload($cached['payload'], $cached['status']);
                }
                $responsePayload = api_build_success_payload($articleService->trashArticle($articleId), $requestId);
                $statusCode = 200;
            } else {
                throw new ApiException('not_found', '接口不存在', 404);
            }
        } else {
            throw new ApiException('not_found', '接口不存在', 404);
        }
    } else {
        throw new ApiException('not_found', '接口不存在', 404);
    }

    if ($shouldStoreIdempotency && $routeKey !== null && $request->getIdempotencyKey()) {
        api_store_idempotency_response(
            $db,
            $request->getIdempotencyKey(),
            $routeKey,
            api_request_hash($request->getBody()),
            $responsePayload,
            $statusCode
        );
    }
} catch (ApiException $e) {
    $statusCode = $e->getHttpStatus();
    $responsePayload = api_build_error_payload($e->getErrorCode(), $e->getMessage(), $requestId, $e->getDetails());
} catch (Throwable $e) {
    $statusCode = 500;
    $responsePayload = api_build_error_payload('internal_error', '服务器内部错误', $requestId);
    write_log('API v1 error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'ERROR');
}

api_emit_payload($responsePayload, $statusCode);

function nav_serialize_site_row(array $row): array {
    $isRec = $row['is_recommended'] ?? false;
    if (is_string($isRec)) {
        $isRec = $isRec !== '' && $isRec !== 'f' && $isRec !== '0';
    } else {
        $isRec = (bool) $isRec;
    }
    $tagsRaw = trim((string) ($row['tags'] ?? ''));
    $tags = $tagsRaw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $tagsRaw))));
    $socialRaw = trim((string) ($row['social_links'] ?? ''));
    $social = (object) [];
    if ($socialRaw !== '') {
        $decoded = json_decode($socialRaw, true);
        if (is_array($decoded)) {
            $social = $decoded;
        }
    }
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'url' => $row['url'],
        'description' => $row['description'] ?? '',
        'icon' => $row['icon'] ?? '',
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'category_id' => isset($row['category_id']) ? (int) $row['category_id'] : null,
        'is_recommended' => $isRec,
        'tags' => $tags,
        'rating' => isset($row['rating']) ? (float) $row['rating'] : 0.0,
        'social_links' => $social,
        'screenshot_url' => $row['screenshot_url'] ?? '',
    ];
}

function api_detect_client_ip(): string {
    $candidates = [
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }

        $parts = array_map('trim', explode(',', $candidate));
        foreach ($parts as $part) {
            if ($part !== '') {
                return mb_substr($part, 0, 100);
            }
        }
    }

    return '';
}

function api_handle_idempotency_if_needed(PDO $db, ApiRequest $request, string $routeKey): ?array {
    $idempotencyKey = $request->getIdempotencyKey();
    if ($idempotencyKey === null || $idempotencyKey === '') {
        return null;
    }

    return api_load_idempotency_response(
        $db,
        $idempotencyKey,
        $routeKey,
        api_request_hash($request->getBody())
    );
}
