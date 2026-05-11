@extends('seo.layout')

@section('content')
    <nav class="crumb">
        <a href="/">首页</a>
        @if (!empty($site['category']))
            › <a href="/c/{{ $site['category']['slug'] }}">{{ $site['category']['name'] }}</a>
        @endif
        › <span>{{ $site['name'] }}</span>
    </nav>

    <h2>{{ $site['icon'] }} {{ $site['name'] }}
        @if ($site['is_recommended'])
            <span class="badge">★ 推荐</span>
        @endif
    </h2>

    @if ($site['description'])
        <p style="color:#94a3b8;font-size:15px;line-height:1.8">{{ $site['description'] }}</p>
    @endif

    @if (!empty($site['rating']) && $site['rating'] > 0)
        <p style="margin:16px 0"><strong style="color:#94a3b8;font-weight:500">评分：</strong>
            <span style="color:#ffb84d;font-weight:600">{{ number_format((float) $site['rating'], 1) }} / 5</span>
        </p>
    @endif

    @if (!empty($site['tags']))
        <p style="margin:16px 0">
            <strong style="color:#94a3b8;font-weight:500">标签：</strong>
            @foreach ($site['tags'] as $tag)
                <span style="display:inline-block;background:rgba(124,58,237,.1);color:#c4b5fd;font-size:12px;padding:4px 10px;border-radius:999px;margin:0 6px 6px 0">#{{ $tag }}</span>
            @endforeach
        </p>
    @endif

    @if (!empty($site['social_links']) && is_array($site['social_links']))
        <p style="margin:16px 0">
            <strong style="color:#94a3b8;font-weight:500">社交：</strong>
            @foreach ($site['social_links'] as $platform => $url)
                <a href="{{ $url }}" target="_blank" rel="noopener nofollow" style="color:#00d4ff;text-decoration:none;margin-right:12px">{{ $platform }}</a>
            @endforeach
        </p>
    @endif

    <p style="margin-top:32px">
        <a href="{{ $site['url'] }}" target="_blank" rel="noopener nofollow"
           style="display:inline-block;padding:12px 24px;background:#00d4ff;color:#0a0e1a;border-radius:8px;text-decoration:none;font-weight:600">
            访问官网 →
        </a>
    </p>
@endsection
