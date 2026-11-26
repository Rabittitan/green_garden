{{-- Logo kênh vẽ bằng SVG nội tuyến (không cần CDN / Font Awesome). Biến: $channel --}}
@switch($channel)
    @case('zalo')
        <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="24" fill="#0068ff"/>
            <text x="24" y="29.5" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="15" fill="#fff">Zalo</text></svg>
        @break
    @case('facebook')
        <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="24" fill="#1877f2"/>
            <path fill="#fff" d="M26.6 38V25.9h4.1l.6-4.7h-4.7v-3c0-1.4.4-2.3 2.4-2.3h2.5v-4.2c-.4-.1-1.9-.2-3.6-.2-3.6 0-6 2.2-6 6.2v3.5h-4.1v4.7h4.1V38z"/></svg>
        @break
    @case('tiktok')
        <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="24" fill="#111"/>
            <path fill="#25f4ee" d="M20.5 22.3v-1.4a9 9 0 0 0-1.2-.1 8.4 8.4 0 0 0-4.6 15.4 8.4 8.4 0 0 1 5.8-13.9z"/>
            <path fill="#fe2c55" d="M28.9 12.5h-3.4v17a3.6 3.6 0 1 1-2.6-3.5v-3.5a7.1 7.1 0 1 0 6 7v-9.9a9.4 9.4 0 0 0 5.4 1.7V18a5.5 5.5 0 0 1-5.4-5.5z"/>
            <path fill="#fff" d="M27.6 11.2h-3.4v17a3.6 3.6 0 1 1-2.6-3.5v-3.5a7.1 7.1 0 1 0 6 7v-9.9a9.4 9.4 0 0 0 5.4 1.7v-3.4a5.5 5.5 0 0 1-5.4-5.4z"/></svg>
        @break
@endswitch
