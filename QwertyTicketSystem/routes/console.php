<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('send-mail {to=mthu35997@gmail.com}', function (string $to): void {
    Mail::raw('Congrats for sending test email from your hosting SMTP server!', function ($message) use ($to): void {
        $message
            ->to($to)
            ->subject('Hosting SMTP Test');
    });

    $this->info('Hosting SMTP test email sent to '.$to.'.');
})->purpose('Send a hosting SMTP test email');
