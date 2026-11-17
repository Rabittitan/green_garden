{{-- Thẻ hiển thị 1 bài viết. Nhận biến $article --}}
<div class="card plant-card h-100">
    <a href="{{ route('news.show', $article->id) }}">
        <img src="{{ $article->image }}" class="card-img-top" alt="{{ $article->title }}"
             onerror="this.onerror=null;this.src='https://placehold.co/800x400/e8f5e9/2e7d32?text=GreenLife'">
    </a>
    <div class="card-body d-flex flex-column">
        <small class="text-muted"><i class="fa fa-calendar"></i> {{ $article->created_at->format('d/m/Y') }}</small>
        <h6 class="card-title mt-1">
            <a href="{{ route('news.show', $article->id) }}" class="text-decoration-none text-dark">{{ $article->title }}</a>
        </h6>
        <p class="card-text text-secondary small">{{ $article->summary }}</p>
        <a href="{{ route('news.show', $article->id) }}" class="btn btn-outline-green btn-sm mt-auto align-self-start">Đọc tiếp</a>
    </div>
</div>