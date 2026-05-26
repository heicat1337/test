<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:site_name" content="玄猫Web3">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:url" content="{{ $canonical }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if (!empty($ogImage))
<meta property="og:image" content="{{ $ogImage }}">
<meta name="twitter:image" content="{{ $ogImage }}">
@endif
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endforeach
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0a0e1a;color:#e2e8f0;margin:0;padding:0;line-height:1.6}
.wrap{max-width:1280px;margin:0 auto;padding:24px}
header.site-h{display:flex;align-items:center;gap:24px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:32px}
header.site-h h1{font-size:22px;margin:0}
header.site-h h1 a{color:#00d4ff;text-decoration:none}
nav.tabs a{color:#94a3b8;text-decoration:none;margin-right:16px;font-size:14px}
nav.tabs a:hover{color:#e2e8f0}
h2{font-size:24px;margin:32px 0 16px;color:#e2e8f0}
h3.cat-name{font-size:18px;margin:0;color:#e2e8f0}
.cat{margin-bottom:36px}
.cat-h{display:flex;align-items:center;gap:10px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:16px}
.cat-icon{font-size:20px}
.cat-meta{margin-left:auto;color:#64748b;font-size:12px}
ul.sites{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
ul.sites li{padding:14px 16px;border:1px solid rgba(255,255,255,.08);border-radius:10px;background:rgba(255,255,255,.04)}
ul.sites li a{color:#e2e8f0;font-weight:600;text-decoration:none;font-size:15px}
ul.sites li a:hover{color:#00d4ff;text-decoration:underline}
ul.sites li p{color:#94a3b8;font-size:13px;margin:6px 0 0}
ul.sites li .badge{display:inline-block;background:rgba(0,255,136,.12);color:#00ff88;font-size:11px;padding:2px 6px;border-radius:999px;margin-left:6px}
nav.crumb{font-size:13px;color:#64748b;margin-bottom:12px}
nav.crumb a{color:#94a3b8;text-decoration:none}
nav.crumb a:hover{color:#00d4ff}
nav.cat-cross{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
nav.cat-cross a{padding:6px 12px;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#94a3b8;text-decoration:none;font-size:13px}
nav.cat-cross a:hover{color:#00d4ff;border-color:rgba(0,212,255,.3)}
footer{margin-top:48px;padding-top:24px;border-top:1px solid rgba(255,255,255,.08);text-align:center;color:#64748b;font-size:13px}
</style>
</head>
<body>
<div class="wrap">
    <header class="site-h">
        <h1><a href="/">玄猫Web3</a></h1>
        <nav class="tabs"><a href="/">导航</a><a href="/articles">文章</a></nav>
    </header>

    @yield('content')

    <footer>玄猫Web3 — 探索去中心化世界 · <a href="{{ $baseUrl }}/sitemap.xml" style="color:#94a3b8">sitemap</a></footer>
</div>
</body>
</html>
