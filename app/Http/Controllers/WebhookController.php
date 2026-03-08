<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Nutgram $bot): Response
    {
        // Nutgram cannot auto-detect running mode inside Laravel — must be explicit
        $bot->setRunningMode(Webhook::class);
        $bot->run();

        return response('', 200);
    }
}
