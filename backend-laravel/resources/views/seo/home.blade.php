@extends('seo.layout')

@section('content')
    <h2>Web3 项目导航 · {{ count($cats) }} 个分类 · {{ $totalSites }} 个精选项目</h2>
    <p style="color:#94a3b8;margin-bottom:32px">覆盖交易所、DeFi、DEX、NFT、钱包、L2 扩容、跨链桥、数据分析、开发工具、DAO 治理、安全与新闻资讯等 Web3 全生态。</p>

    @foreach ($cats as $cat)
        @include('seo._partials.category_section', ['cat' => $cat])
    @endforeach
@endsection
