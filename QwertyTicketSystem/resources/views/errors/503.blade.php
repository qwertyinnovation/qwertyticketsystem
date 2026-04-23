@php
    $statusCode = 503;
    $statusLabel = 'Service unavailable';
    $eyebrow = 'Temporary interruption';
    $title = 'The service is temporarily unavailable.';
    $message = 'The application may be under maintenance or experiencing temporary load. Please wait a moment and try again.';
@endphp

@include('errors.layout')
