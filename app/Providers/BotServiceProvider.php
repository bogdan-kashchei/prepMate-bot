<?php

declare(strict_types=1);

namespace App\Providers;

use App\Conversations\InterviewConversation;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

class BotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Nutgram::class, function () {
            $token = config('services.telegram.token', '');

            $cacheDir = storage_path('framework/nutgram');
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $cache = new Psr16Cache(
                new FilesystemAdapter('nutgram', 3600, $cacheDir)
            );

            $bot = new Nutgram($token, new Configuration(cache: $cache));

            $this->registerHandlers($bot);

            return $bot;
        });
    }

    public function boot(): void
    {
        // Nutgram is resolved lazily — only when WebhookController requests it
    }

    private function registerHandlers(Nutgram $bot): void
    {
        // /start command
        $bot->onCommand('start', function (Nutgram $bot) {
            (new UserService())->createOrUpdate(
                telegramId:   $bot->userId(),
                username:     $bot->user()?->username      ?? '',
                firstName:    $bot->user()?->first_name    ?? '',
                languageCode: $bot->user()?->language_code ?? '',
            );

            $name = $bot->user()?->first_name ?? 'there';

            $bot->sendMessage(
                text: "👋 Hello, {$name}!\n\n" .
                      "Welcome to the *Interview Practice Bot*. I'll help you prepare for technical interviews " .
                      "by asking real questions and giving you AI-powered feedback on your answers.\n\n" .
                      "Use /menu to start practicing or /progress to see your stats.",
                parse_mode: 'Markdown',
            );

            InterviewConversation::begin($bot);
        });

        // /menu command
        $bot->onCommand('menu', function (Nutgram $bot) {
            (new UserService())->createOrUpdate(
                telegramId:   $bot->userId(),
                username:     $bot->user()?->username      ?? '',
                firstName:    $bot->user()?->first_name    ?? '',
                languageCode: $bot->user()?->language_code ?? '',
            );

            InterviewConversation::begin($bot);
        });

        // /progress command
        $bot->onCommand('progress', function (Nutgram $bot) {
            try {
                $userService = new UserService();
                $userId      = $userService->getIdByTelegramId($bot->userId());

                if ($userId === null) {
                    $bot->sendMessage('No data yet. Use /start to begin practicing!');
                    return;
                }

                $stats        = $userService->getProgress($userId);
                $selfAnswered = $stats['self_answered'] ?? 0;
                $viewedKnew   = $stats['viewed_knew']   ?? 0;
                $viewedDidnt  = $stats['viewed_didnt']  ?? 0;
                $total        = $selfAnswered + $viewedKnew + $viewedDidnt;

                if ($total === 0) {
                    $bot->sendMessage("You haven't answered any questions yet.\nUse /menu to start practicing!");
                    return;
                }

                $lines   = ["📊 *Your Progress*\n"];
                $lines[] = "✍️ *Answered yourself:* {$selfAnswered} question(s)";
                $lines[] = "👁 *Viewed answers:* ✅ {$viewedKnew} knew  /  ❌ {$viewedDidnt} didn't know";

                if (!empty($stats['by_category'])) {
                    $lines[] = "\n*By category:*";

                    foreach ($stats['by_category'] as $category => $data) {
                        $label    = strtoupper($category);
                        $catSelf  = $data['self_answered'];
                        $catKnew  = $data['viewed_knew'];
                        $catDidnt = $data['viewed_didnt'];
                        $catTotal = $catSelf + $catKnew + $catDidnt;
                        $pct      = $data['knew_pct'];

                        $parts = [];
                        if ($catSelf  > 0) $parts[] = "✍️ {$catSelf}";
                        if ($catKnew  > 0) $parts[] = "✅ {$catKnew}";
                        if ($catDidnt > 0) $parts[] = "❌ {$catDidnt}";

                        $pctStr  = $pct !== null ? " — {$pct}% knew" : '';
                        $lines[] = "• *{$label}*: {$catTotal} total (" . implode(', ', $parts) . "){$pctStr}";
                    }
                }

                $lines[] = "\nKeep it up! Use /menu to continue.";

                $bot->sendMessage(implode("\n", $lines), parse_mode: 'Markdown');
            } catch (\Throwable $e) {
                logger()->error('[Progress] ' . $e->getMessage());
                $bot->sendMessage('Could not load your progress. Please try again later.');
            }
        });

        // Fallback for unrecognised messages outside a conversation
        $bot->fallback(function (Nutgram $bot) {
            $bot->sendMessage("I didn't understand that. Use /menu to start an interview session or /progress to see your stats.");
        });
    }
}
