@extends('layouts.admin')
@section('title', 'Hỏi đáp')

@section('content')
<h4 class="text-green fw-bold mb-3"><i class="fa fa-comments"></i> Hỏi đáp & bình luận</h4>
<div class="mb-3 d-flex gap-1">
    @foreach(['unanswered' => 'Chưa trả lời', 'hidden' => 'Đã ẩn', 'all' => 'Tất cả'] as $v => $l)
        <a href="{{ route('admin.comments.index', ['filter' => $v]) }}" class="btn btn-sm {{ $filter === $v ? 'btn-success' : 'btn-outline-success' }}">{{ $l }}</a>
    @endforeach
</div>
@forelse($comments as $c)
    <div class="card card-body shadow-sm mb-3 {{ $c->status->value === 'hidden' ? 'opacity-75' : '' }}">
        <div class="d-flex justify-content-between">
            <div><strong>{{ $c->user->name }}</strong> hỏi về
                @if($c->commentable instanceof \App\Models\Plant)
                    cây <a href="{{ route('plants.show', $c->commentable_id) }}" target="_blank">{{ $c->commentable->name }}</a>
                @elseif($c->commentable)
                    bài <a href="{{ route('news.show', $c->commentable_id) }}" target="_blank">{{ $c->commentable->title }}</a>
                @endif
                @if($c->status->value === 'hidden')<span class="badge bg-secondary">Đã ẩn</span>@endif
            </div>
            <span class="small text-muted">@vndt($c->created_at)</span>
        </div>
        <div style="white-space: pre-line">{{ $c->content }}</div>
        @foreach($c->replies as $r)
            <div class="ms-4 mt-1 small {{ $r->is_pinned ? 'text-success' : 'text-muted' }}">↳ <strong>{{ $r->user->name }}</strong>: {{ $r->content }}
                @if($r->status->value === 'hidden') <span class="badge bg-secondary">ẩn</span> @endif
                <form action="{{ route('admin.comments.toggle', $r) }}" method="POST" class="d-inline">@csrf<button class="btn btn-link btn-sm p-0">{{ $r->status->value === 'hidden' ? 'Hiện' : 'Ẩn' }}</button></form></div>
        @endforeach
        <div class="d-flex gap-2 mt-2">
            <form action="{{ route('admin.comments.reply', $c) }}" method="POST" class="d-flex gap-1 flex-grow-1">@csrf
                <input name="content" class="form-control form-control-sm" placeholder="Trả lời với tư cách shop (được ghim)" required>
                <button class="btn btn-sm btn-green text-nowrap">Trả lời</button></form>
            <form action="{{ route('admin.comments.toggle', $c) }}" method="POST">@csrf
                <button class="btn btn-sm btn-outline-secondary">{{ $c->status->value === 'hidden' ? 'Hiện' : 'Ẩn' }}</button></form>
        </div>
    </div>
@empty
    <div class="alert alert-light">Không có câu hỏi nào.</div>
@endforelse
{{ $comments->links() }}
@endsection
