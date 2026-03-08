<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.telegram.webhook_secret', '');
        $receivedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (empty($expectedSecret) || !hash_equals($expectedSecret, $receivedSecret)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
