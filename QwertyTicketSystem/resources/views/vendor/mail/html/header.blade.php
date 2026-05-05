@props(['url'])

@php
    $logoUrl = trim((string) env('MAIL_LOGO_URL', ''));

    if ($logoUrl === '') {
        $logoUrl = rtrim((string) config('app.url', ''), '/').'/images/qwerty-logo.png';
    }
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl !== '')
<img src="{{ $logoUrl }}" class="logo" alt="{{ config('app.name') }} Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>

