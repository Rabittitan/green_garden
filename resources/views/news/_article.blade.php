{{-- Thân bài (dùng chung trang tin tức và trang xem trước của admin). Biến: $article (đã nạp plants) --}}
<header class="gg-article-head mb-3">
    <h1>{{ $article->title }}</h1>
    <small class="text-muted"><i class="fa fa-calendar"></i> {{ \App\Support\Format::datetime($article->published_at ?? $article->created_at, 'd/m/Y') }}
        @if($article->author) · <i class="fa fa-user-pen"></i> {{ $article->author->name }} @endif</small>
</header>

@if($article->image)
    <div class="gg-article-cover my-3">
        <img src="{{ $article->image }}" alt="{{ $article->title }}"
             onerror="this.onerror=null;this.src='https://placehold.co/800x400/e8f5e9/2e7d32?text=GreenGarden'">
    </div>
@endif

{{-- Markdown -> HTML an toàn (Article::renderedContent: bỏ HTML gõ tay, chặn link javascript:) --}}
<div class="gg-article-body">{{ $article->renderedContent() }}</div>

@php $mentioned = $article->plants->filter(fn ($p) => $p->is_active); @endphp
@if($mentioned->isNotEmpty())
    <div class="gg-panel gg-panel-body mt-4">
        <div class="fw-bold mb-2"><i class="fa fa-seedling text-green"></i> Cây được nhắc tới trong bài</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($mentioned as $p)
                <a href="{{ route('plants.show', $p->id) }}" class="btn btn-sm btn-outline-green">{{ $p->name }} · @money($p->price)</a>
            @endforeach
        </div>
    </div>
@endif
