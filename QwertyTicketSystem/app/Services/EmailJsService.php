<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmailJsService
{
    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function send(array $templateParams): void
    {
        $serviceId = trim((string) config('services.emailjs.service_id'));
        $templateId = trim((string) config('services.emailjs.template_id'));
        $publicKey = trim((string) config('services.emailjs.public_key'));
        $privateKey = trim((string) config('services.emailjs.private_key'));
        $endpoint = trim((string) config('services.emailjs.endpoint', 'https://api.emailjs.com/api/v1.0/email/send'));

        if ($serviceId === '' || $templateId === '' || $publicKey === '') {
            throw new RuntimeException('EmailJS is not configured. Missing service_id, template_id, or public_key.');
        }

        $payload = [
            'service_id' => $serviceId,
            'template_id' => $templateId,
            'user_id' => $publicKey,
            'template_params' => $templateParams,
        ];

        if ($privateKey !== '') {
            $payload['accessToken'] = $privateKey;
        }

        $response = Http::asJson()
            ->timeout(15)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('EmailJS send failed: '.$response->status().' '.$response->body());
        }
    }
}

