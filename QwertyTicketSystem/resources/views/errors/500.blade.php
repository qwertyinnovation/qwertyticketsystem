@php
    $statusCode = 500;
    $statusLabel = 'Server error';
    $eyebrow = 'Unexpected problem';
    $title = 'Something went wrong on our side.';
    $message = 'The request could not be completed because the server encountered an unexpected error. Please try again in a moment.';
@endphp

@include('errors.layout')
