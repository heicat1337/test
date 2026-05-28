@extends('seo.layout')

@section('content')
    <nav class="crumb">
        <a href="/">首页</a>
        › <span>文章</span>
    </nav>

    <h2>Web3 文章</h2>
    <p style="color:#94a3b8;margin:0 0 28px">探索最新的 Web3 行业资讯、技术分析与深度研究。</p>

    @if (count($articles) > 0)
        <ul class="sites">
            @foreach ($articles as $article)
                <li>
                    <a href="/articles/{{ $article['slug'] }}">{{ $article['title'] }}</a>
                    <p>
                        @if ($article['category'])
                            <span>{{ $article['category'] }}</span>
                        @endif
                        @if ($article['published_human'])
                            @if ($article['category']) · @endif
                            <time datetime="{{ $article['published_at'] }}">{{ $article['published_human'] }}</time>
                        @endif
                        @if ($article['author'])
                            · <span>{{ $article['author'] }}</span>
                        @endif
                    </p>
                    @if ($article['description'])
                        <p>{{ $article['description'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <p style="color:#94a3b8">暂无已发布文章。</p>
    @endif
@endsection
