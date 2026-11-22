{{-- Tên shop: nếu .env chưa đổi APP_NAME (còn "Laravel") vẫn hiện đúng tên shop cho khách --}}
@php($shop = config('app.name') === 'Laravel' ? 'GreenLife Shop' : config('app.name'))
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $shop }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Cây cảnh phong thủy & nội thất · Hotline 0900 000 001<br>
© {{ date('Y') }} {{ $shop }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
