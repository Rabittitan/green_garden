@props(['url'])
{{-- Đầu mail: tên shop dạng chữ (không dùng ảnh ngoài -> hiện được cả khi trình mail chặn ảnh) --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<span class="brand-leaf">🌿</span> {{ $slot }}
</a>
</td>
</tr>
