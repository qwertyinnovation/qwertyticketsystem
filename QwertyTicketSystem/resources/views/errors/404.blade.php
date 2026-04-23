@php
    $statusCode = 404;
    $statusLabel = 'Page not found';
    $eyebrow = 'Missing page';
    $title = 'The page you are looking for could not be found.';
    $message = 'The link may be outdated, the resource may have been removed, or the address may be incorrect. Please check the URL and try again.';
@endphp

@include('errors.layout')
