{{-- Hỏi đáp / bình luận dùng chung. Biến: $comments (paginator câu hỏi gốc, đã nạp replies đã duyệt), $postRoute (URL gửi) --}}
<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold"><i class="fa fa-comments text-green"></i> {{ $title ?? 'Hỏi đáp' }}</div>
    <div class="card-body">
        @auth
            <form action="{{ $postRoute }}" method="POST" class="mb-3">@csrf
                <textarea name="content" rows="2" class="form-control mb-2 @error('content') is-invalid @enderror" maxlength="2000"
                          placeholder="{{ $placeholder ?? 'Đặt câu hỏi về cây này (cách chăm, vị trí đặt...)' }}" required>{{ old('parent_id') ? '' : old('content') }}</textarea>
                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <button class="btn btn-sm btn-green">Gửi</button>
            </form>
        @else
            <p class="text-muted"><a href="{{ route('login') }}">Đăng nhập</a> để đặt câu hỏi.</p>
        @endauth

        @forelse($comments as $c)
            <div class="border-top pt-2 mt-2">
                <div><strong>{{ $c->user->name }}</strong> <span class="small text-muted">@vndt($c->created_at)</span>
                    @if(auth()->id() === $c->user_id)
                        <form action="{{ route('comments.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa bình luận?')">@csrf @method('DELETE')
                            <button class="btn btn-link btn-sm text-danger p-0 ms-2">Xóa</button></form>
                    @endif
                </div>
                <div style="white-space: pre-line">{{ $c->content }}</div>
                @foreach($c->replies->sortByDesc('is_pinned') as $r)
                    <div class="ms-4 mt-2 p-2 rounded {{ $r->is_pinned ? 'bg-success bg-opacity-10 border-start border-success border-3' : 'bg-light' }}">
                        <strong>{{ $r->user->name }}</strong> @if($r->is_pinned)<span class="badge bg-success">Shop trả lời</span>@endif
                        <span class="small text-muted">@vndt($r->created_at)</span>
                        <div style="white-space: pre-line">{{ $r->content }}</div>
                    </div>
                @endforeach
                @auth
                    <form action="{{ $postRoute }}" method="POST" class="ms-4 mt-1 d-flex gap-1">@csrf
                        <input type="hidden" name="parent_id" value="{{ $c->id }}">
                        <input name="content" class="form-control form-control-sm" placeholder="Trả lời..." maxlength="2000" required>
                        <button class="btn btn-sm btn-outline-green">Trả lời</button>
                    </form>
                @endauth
            </div>
        @empty
            <p class="text-muted mb-0">Chưa có câu hỏi nào.</p>
        @endforelse
        <div class="mt-2">{{ $comments->links() }}</div>
    </div>
</div>
