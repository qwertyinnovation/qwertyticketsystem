@php
    $statusCode = 403;
    $statusLabel = 'Access denied';
    $eyebrow = 'Permission required';
    $title = 'You do not have access to this page.';
    $message = 'Your account does not have permission to view or perform this action. If you believe this is incorrect, contact the service desk administrator.';
@endphp

@include('errors.layout')
