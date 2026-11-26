{{--
    Bong bóng mạng xã hội góc phải dưới (Admin → Kênh liên hệ). Biến $contactChannels do View composer cấp.
    Dùng <details>: bấm là mở / đóng, không cần JS hay CDN. Link mở tab mới, không gửi referrer.
--}}
@if($contactChannels->isNotEmpty())
    <details class="gg-social" data-testid="social-bubble">
        <summary class="gg-social-toggle" aria-label="Liên hệ với shop qua mạng xã hội">
            <svg class="gg-social-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3C6.5 3 2 6.9 2 11.6c0 2.6 1.4 5 3.7 6.5L5 21.5l3.9-2.1c1 .3 2 .4 3.1.4 5.5 0 10-3.9 10-8.6S17.5 3 12 3zm-4.5 9.9a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6zm4.5 0a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6zm4.5 0a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6z"/></svg>
            <svg class="gg-social-close" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4L13.4 12l4.9-4.9a1 1 0 0 0 0-1.4z"/></svg>
        </summary>
        <ul class="gg-social-list">
            @foreach($contactChannels as $c)
                <li><a href="{{ $c->url }}" target="_blank" rel="noopener noreferrer" class="gg-social-item" aria-label="{{ $c->label() }} của shop (mở tab mới)">
                    @include('partials.social-icon', ['channel' => $c->channel])<span>{{ $c->label() }}</span></a></li>
            @endforeach
        </ul>
    </details>
@endif
