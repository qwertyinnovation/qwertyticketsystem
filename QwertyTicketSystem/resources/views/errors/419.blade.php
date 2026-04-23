@php
    $statusCode = 419;
    $statusLabel = 'Session expired';
    $eyebrow = 'Please try again';
    $title = 'Your session has expired.';
    $message = 'Refresh the page, confirm your information again, and resubmit the form. If the problem continues, sign in again before retrying.';
@endphp

@include('errors.layout')
