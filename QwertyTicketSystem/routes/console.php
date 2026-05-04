<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use App\Services\EmailJsService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('send-mail {to=mthu35997@gmail.com}', function (string $to): void {
    /** @var EmailJsService $emailJs */
    $emailJs = App::make(EmailJsService::class);

    $emailJs->send([
        'to_email' => $to,
        'from_email' => (string) config('mail.from.address', ''),
        'from_name' => (string) config('mail.from.name', ''),
        'subject' => 'You are awesome!',
        'message' => 'Congrats for sending test email with EmailJS!',
    ]);

    $this->info('EmailJS test email sent to '.$to.'.');
})->purpose('Send an EmailJS test email');
