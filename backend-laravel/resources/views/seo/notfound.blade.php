@extends('seo.layout', ['jsonLd' => [], 'canonical' => $baseUrl . '/', 'description' => '该页面不存在或已下架'])

@section('content')
    <h2>{{ $message }}</h2>
    <p><a href="/" style="color:#00d4ff">← 返回首页</a></p>
@endsection
