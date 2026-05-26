@extends('seo.layout')

@section('content')
    <nav class="crumb">
        <a href="/">首页</a>
        › <a href="/articles">文章</a>
        › <span>{{ $article['title'] }}</span>
    </nav>

    <article>
        <h2>{{ $article['title'] }}</h2>

        <p style="color:#64748b;font-size:13px;margin:0 0 24px">
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
