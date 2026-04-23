@php
    $statusCode = $exception->getStatusCode();
    $statusLabel = 'Request error';
    $eyebrow = 'Request could not be completed';
    $title = 'This request could not be completed.';
    $message = 'Please check the page or action you requested and try again. If the issue persists, contact the service desk team.';
@endphp

@include('errors.layout')
