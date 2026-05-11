<?php

use App\Http\Controllers\Api\Admin\ArticleAdminController;
use App\Http\Controllers\Api\Admin\CatalogController;
use App\Http\Controllers\Api\Admin\JobController;
use App\Http\Controllers\Api\Admin\TaskController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\NavController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// 公开 API（无需鉴权）
// =========================================================================

// 导航
Route::prefix('v1/nav')->name('api.v1.nav.')->group(function () {
    Route::get('categories',  [NavController::class, 'categories'])->name('categories');
    Route::get('sites',       [NavController::class, 'sites'])->name('sites');
    Route::get('sites/{id}',  [NavController::class, 'site'])->name('site')->where('id', '\d+');
    Route::get('recommended', [NavController::class, 'recommended'])->name('recommended');
});

// 文章（公开列表 + 详情）
Route::prefix('v1/articles')->name('api.v1.articles.')->group(function () {
    Route::get('/',              [ArticleController::class, 'index'])->name('index');
    Route::get('by-slug/{slug}', [ArticleController::class, 'showBySlug'])
        ->name('by-slug')
        ->where('slug', '[A-Za-z0-9_\-]+');
});

// =========================================================================
// 鉴权 API（Authorization: Bearer <token>）
// =========================================================================
// 与老 backend api/v1/index.php 中的 catalog / tasks / jobs / articles 管理路由
// 行为对齐；token 通过 api_tokens 表 SHA256 hash 校验，scope 粒度授权。
// =========================================================================

Route::prefix('v1')->middleware('api.token')->group(function () {
    // Catalog
    Route::get('catalog', [CatalogController::class, 'index'])
        ->middleware('api.scope:catalog:read');

    // Tasks
    Route::prefix('tasks')->group(function () {
        Route::get('/',         [TaskController::class, 'index'])->middleware('api.scope:tasks:read');
        Route::post('/',        [TaskController::class, 'store'])->middleware('api.scope:tasks:write');
        Route::get('{id}',      [TaskController::class, 'show'])->middleware('api.scope:tasks:read')->where('id', '\d+');
        Route::patch('{id}',    [TaskController::class, 'update'])->middleware('api.scope:tasks:write')->where('id', '\d+');
        Route::post('{id}/start',   [TaskController::class, 'start'])->middleware('api.scope:tasks:write')->where('id', '\d+');
        Route::post('{id}/stop',    [TaskController::class, 'stop'])->middleware('api.scope:tasks:write')->where('id', '\d+');
        Route::post('{id}/enqueue', [TaskController::class, 'enqueue'])->middleware('api.scope:tasks:write')->where('id', '\d+');
        Route::get('{id}/jobs', [TaskController::class, 'jobs'])->middleware('api.scope:tasks:read')->where('id', '\d+');
    });

    // Jobs（单 job 详情）
    Route::get('jobs/{id}', [JobController::class, 'show'])
        ->middleware('api.scope:jobs:read')
        ->where('id', '\d+');

    // Articles 管理路由（带 /admin 前缀避免与公开 list/by-slug 冲突）
    Route::prefix('admin/articles')->group(function () {
        Route::get('/',           [ArticleAdminController::class, 'index'])->middleware('api.scope:articles:read');
        Route::post('/',          [ArticleAdminController::class, 'store'])->middleware('api.scope:articles:write');
        Route::get('{id}',        [ArticleAdminController::class, 'show'])->middleware('api.scope:articles:read')->where('id', '\d+');
        Route::patch('{id}',      [ArticleAdminController::class, 'update'])->middleware('api.scope:articles:write')->where('id', '\d+');
        Route::post('{id}/review',  [ArticleAdminController::class, 'review'])->middleware('api.scope:articles:publish')->where('id', '\d+');
        Route::post('{id}/publish', [ArticleAdminController::class, 'publish'])->middleware('api.scope:articles:publish')->where('id', '\d+');
        Route::post('{id}/trash',   [ArticleAdminController::class, 'trash'])->middleware('api.scope:articles:write')->where('id', '\d+');
    });
});
