<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\NavController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 后台 token 鉴权探针
Route::get('/user', fn (Request $r) => $r->user())->middleware('auth:sanctum');

// 公共导航 API（无需鉴权）
// 路径前缀：/api/v1/nav/*
Route::prefix('v1/nav')->name('api.v1.nav.')->group(function () {
    Route::get('categories',  [NavController::class, 'categories'])->name('categories');
    Route::get('sites',       [NavController::class, 'sites'])->name('sites');
    Route::get('sites/{id}',  [NavController::class, 'site'])->name('site')->where('id', '\d+');
    Route::get('recommended', [NavController::class, 'recommended'])->name('recommended');
});

// 公共文章 API（无需鉴权）
// 路径前缀：/api/v1/articles*
Route::prefix('v1/articles')->name('api.v1.articles.')->group(function () {
    Route::get('/',              [ArticleController::class, 'index'])->name('index');
    Route::get('by-slug/{slug}', [ArticleController::class, 'showBySlug'])
        ->name('by-slug')
        ->where('slug', '[A-Za-z0-9_\-]+');
});
