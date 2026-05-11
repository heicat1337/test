@extends('seo.layout')

@section('content')
    <nav class="crumb">
        <a href="/">首页</a> › <span>{{ $cat['name'] }}</span>
    </nav>

    <h2>{{ $cat['icon'] }} {{ $cat['name'] }}</h2>
    <p style="color:#94a3b8;margin-bottom:24px">该分类下精选 {{ count($cat['sites']) }} 个 Web3 项目，玄猫Web3 持续更新。</p>

    @include('seo._partials.category_section', ['cat' => $cat])

    @if (count($allCats) > 1)
        <nav class="cat-cross">
            <span style="color:#64748b;font-size:13px;margin-right:8px">其他分类：</span>
            @foreach ($allCats as $other)
                @if ($other['slug'] !== $cat['slug'])
                    <a href="/c/{{ $other['slug'] }}">{{ $other['icon'] }} {{ $other['name'] }}</a>
                @endif
            @endforeach
        </nav>
    @endif
@endsection
