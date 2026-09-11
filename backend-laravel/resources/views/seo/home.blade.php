@extends('seo.layout')

@section('content')
    <form action="/" method="get" style="margin-bottom:24px">
        <label for="q" style="position:absolute;left:-9999px">搜索 Web3 项目</label>
        <input id="q" name="q" value="{{ $q ?? '' }}" placeholder="搜索 Web3 项目"
               style="width:100%;max-width:480px;padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:#111827;color:#e2e8f0">
    </form>
    <h2>Web3 项目导航 · {{ count($cats) }} 个分类 · {{ $totalSites }} 个精选项目</h2>
    <p style="color:#94a3b8;margin-bottom:32px">覆盖交易所、DeFi、DEX、NFT、钱包、L2 扩容、跨链桥、数据分析、开发工具、DAO 治理、安全与新闻资讯等 Web3 全生态。</p>

    @foreach ($cats as $cat)
        @include('seo._partials.category_section', ['cat' => $cat])
    @endforeach
@endsection
