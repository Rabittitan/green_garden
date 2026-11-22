{{-- Thẻ hiển thị 1 bài viết. Nhận biến $article --}}
<div class="card plant-card h-100">
    <a href="{{ route('news.show', $article->id) }}" class="gg-card-media">
        <img src="{{ $article->image }}" alt="{{ $article->title }}" loading="lazy"
             onerror="this.onerror=null;this.src='https://placehold.co/800x400/e8f5e9/2e7d32?text=GreenGarden'">
    </a>
    <div class="card-body d-flex flex-column">
        <small class="text-muted"><i class="fa fa-calendar"></i> {{ $article->created_at->format('d/m/Y') }}</small>
        <h3 class="gg-card-title fs-6 mt-1">
            <a href="{{ route('news.show', $article->id) }}">{{ $article->title }}</a>
        </h3>
        <p class="card-text text-secondary small">{{ $article->summary }}</p>
        <a href="{{ route('news.show', $article->id) }}" class="btn btn-outline-green btn-sm mt-auto align-self-start">Đọc tiếp <i class="fa fa-arrow-right ms-1"></i></a>
    </div>
</div>
