<?php

use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// =========================================================================
// 爬虫专属 SSR（Phase 8 - 替代老 backend/render_seo.php）
// =========================================================================
// nginx 内部 rewrite：UA 命中爬虫后 GET / -> /__seo/home，
// GET /c/:slug -> /__seo/category/:slug，GET /project/:id -> /__seo/project/:id。
// 真实用户走 Vue SPA 不会命中这些路由。
// =========================================================================

Route::prefix('__seo')->name('seo.')->group(function () {
    Route::get('home',              [SeoController::class, 'home'])->name('home');
    Route::get('category/{slug}',   [SeoController::class, 'category'])->name('category');
    Route::get('project/{id}',      [SeoController::class, 'project'])->name('project')->where('id', '\d+');
});
