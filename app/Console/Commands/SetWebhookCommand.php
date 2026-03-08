<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    protected $signature   = 'bot:set-webhook {url? : The public HTTPS URL for the webhook}';
    protected $description = 'Register the Telegram webhook URL';

    public function handle(): int
    {
        $token  = config('services.telegram.token', '');
        $secret = config('services.telegram.webhook_secret', '');

        if (empty($token)) {
            $this->error('TELEGRAM_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $url = $this->argument('url') ?? $this->ask('Enter the public HTTPS webhook URL (e.g. https://abc.trycloudflare.com/webhook)');

        if (empty($url)) {
            $this->error('Webhook URL is required.');
            return self::FAILURE;
        }

        $payload = ['url' => $url];
        if (!empty($secret)) {
            $payload['secret_token'] = $secret;
        }

        $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->error("cURL error: {$curlError}");
            return self::FAILURE;
        }

        $data = json_decode($response, true);

        if ($httpCode === 200 && ($data['ok'] ?? false)) {
            $this->info("Webhook set successfully: {$url}");
            $this->line("Response: " . ($data['description'] ?? 'OK'));
            return self::SUCCESS;
        }

        $this->error("Failed to set webhook (HTTP {$httpCode}):");
        $this->line($response);
        return self::FAILURE;
    }
}
