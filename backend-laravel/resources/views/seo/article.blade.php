@extends('seo.layout')

@section('content')
    <nav class="crumb">
        <a href="/">首页</a>
        › <a href="/articles">文章</a>
        › <span>{{ $article['title'] }}</span>
    </nav>

    <article>
        <h2>{{ $article['title'] }}</h2>

        <p style="color:#94a3b8;font-size:13px;margin:0 0 24px">
            @if ($article['author'])
                <span>{{ $article['author'] }}</span>
            @endif
            @if ($article['published_human'])
                @if ($article['author']) · @endif
                <time datetime="{{ $article['published_at'] }}">{{ $article['published_human'] }}</time>
            @endif
        </p>

        @if ($article['excerpt'])
            <p style="color:#94a3b8;font-size:16px;line-height:1.8;border-left:3px solid #00d4ff;padding-left:16px;margin:0 0 28px">
                {{ $article['excerpt'] }}
            </p>
        @endif

        {{-- 正文是站内 CMS 自有内容（GEOFlow 生成），按 HTML 原样渲染供爬虫抓取真实文本。 --}}
        <div class="article-body" style="font-size:16px;line-height:1.9;color:#cbd5e1">
            {!! $article['content'] !!}
        </div>

        <aside style="margin:40px 0 0;padding:20px;border:1px solid rgba(255,255,255,.08);border-radius:12px;background:rgba(255,255,255,.03)">
            <p style="color:#cbd5e1;font-size:13px;line-height:1.7;margin:0 0 16px">
                <strong>推广披露：</strong>文中或下方推荐的外部网站可能含推荐/联盟链接。本站可能因此获得佣金，这不会额外增加你的费用。内容不构成投资建议。
            </p>
            <p style="margin:0 0 12px">
                <a href="/articles" style="color:#00d4ff">← 返回文章目录</a>
                · <a href="/" style="color:#00d4ff">浏览 Web3 导航</a>
            </p>
            @if (!empty($related))
                <h3 style="font-size:16px;margin:16px 0 8px">同分类文章</h3>
                <ul class="sites">
                    @foreach ($related as $item)
                        <li><a href="/articles/{{ $item['slug'] }}">{{ $item['title'] }}</a>
                            @if ($item['excerpt'])<p>{{ $item['excerpt'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if (!empty($relatedSites))
                <h3 style="font-size:16px;margin:24px 0 8px">相关导航项目</h3>
                <ul class="sites">
                    @foreach ($relatedSites as $site)
                        <li><a href="/project/{{ $site['id'] }}">{{ $site['name'] }}</a>
                            <p>{{ $site['description'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>

        @if (!empty($article['keywords']))
            <p style="margin:32px 0 0">
                <strong style="color:#94a3b8;font-weight:500">关键词：</strong>
                @foreach ($article['keywords'] as $kw)
                    <span style="display:inline-block;background:rgba(124,58,237,.1);color:#c4b5fd;font-size:12px;padding:4px 10px;border-radius:999px;margin:0 6px 6px 0">#{{ $kw }}</span>
                @endforeach
            </p>
        @endif
    </article>
@endsection
