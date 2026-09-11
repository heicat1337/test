@extends('seo.layout')

@section('content')
    <nav class="crumb"><a href="/">首页</a> › {{ $heading }}</nav>
    <h2>{{ $heading }}</h2>
    @foreach ($paragraphs as $p)
        <p style="color:#cbd5e1;line-height:1.8;max-width:720px">{{ $p }}</p>
    @endforeach
@endsection
