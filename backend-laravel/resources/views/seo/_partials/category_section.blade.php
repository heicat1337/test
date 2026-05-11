<div class="cat">
    <div class="cat-h">
        <span class="cat-icon">{{ $cat['icon'] }}</span>
        <h3 class="cat-name"><a href="/c/{{ $cat['slug'] }}" style="color:inherit;text-decoration:none">{{ $cat['name'] }}</a></h3>
        <span class="cat-meta">{{ count($cat['sites']) }} 项</span>
    </div>
    <ul class="sites">
        @foreach ($cat['sites'] as $s)
            <li>
                <a href="{{ $s['url'] }}" target="_blank" rel="noopener nofollow">{{ $s['name'] }}</a>
                @if ($s['is_recommended'] ?? false)
                    <span class="badge">★ 推荐</span>
                @endif
                @if (!empty($s['description']))
                    <p>{{ $s['description'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</div>
