@php
    $statusCode = $exception->getStatusCode();
    $statusLabel = 'Server error';
    $eyebrow = 'Temporary issue';
    $title = 'The application encountered an unexpected problem.';
    $message = 'Please try again shortly. If the error continues, report what you were doing so the service desk team can investigate it.';
@endphp

@include('errors.layout')
