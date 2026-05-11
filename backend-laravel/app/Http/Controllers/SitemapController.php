<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\NavCategory;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = rtrim(config('app.url', 'https://xuaweb3.com'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://xuaweb3.com';
        }

        $urls = [
            [
                'loc' => $baseUrl . '/',
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => $baseUrl . '/articles',
                'changefreq' => 'hourly',
                'priority' => '0.9',
            ],
        ];

        foreach ($this->categoryUrls($baseUrl) as $url) {
            $urls[] = $url;
        }

        foreach ($this->articleUrls($baseUrl) as $url) {
            $urls[] = $url;
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=600');
    }

    private function categoryUrls(string $baseUrl): array
    {
        return NavCategory::query()
            ->select('nav_categories.id', 'nav_categories.slug', 'nav_categories.created_at')
            ->selectRaw('COALESCE(MAX(nav_sites.created_at), nav_categories.created_at) AS lastmod')
            ->leftJoin('nav_sites', 'nav_sites.category_id', '=', 'nav_categories.id')
            ->whereNotNull('nav_categories.slug')
            ->where('nav_categories.slug', '<>', '')
            ->groupBy('nav_categories.id', 'nav_categories.slug', 'nav_categories.created_at', 'nav_categories.sort_order')
            ->orderBy('nav_categories.sort_order')
            ->orderBy('nav_categories.id')
            ->get()
            ->map(fn ($row) => [
                'loc' => $baseUrl . '/c/' . $row->slug,
                'lastmod' => $this->formatDate($row->lastmod),
                'changefreq' => 'weekly',
                'priority' => '0.85',
            ])
            ->all();
    }

    private function articleUrls(string $baseUrl): array
    {
        return Article::query()
            ->select('slug', 'updated_at')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderByDesc(DB::raw('COALESCE(published_at, updated_at)'))
            ->get()
            ->map(fn (Article $article) => [
                'loc' => $baseUrl . '/articles/' . $article->slug,
                'lastmod' => $this->formatDate($article->updated_at),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ])
            ->all();
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return now()->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}
