<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

class ClaudeService
{
    private const API_URL    = 'https://api.anthropic.com/v1/messages';
    private const MODEL      = 'claude-haiku-4-5-20251001';
    private const MAX_TOKENS = 500;
    private const TIMEOUT    = 30;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an experienced technical interviewer. Evaluate the candidate's answer briefly (3–5 sentences).
Point out: what is correct, what is missing, and what could be improved.
Reply in English. Be specific and constructive.
PROMPT;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key', '');
    }

    public function getFeedback(string $question, string $answer): ?string
    {
        if (empty($this->apiKey)) {
            logger()->error('ClaudeService: CLAUDE_API_KEY is not set.');
            return null;
        }

        $payload = [
            'model'      => self::MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => "Interview question: {$question}\n\nCandidate's answer: {$answer}",
                ],
            ],
        ];

        try {
            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $this->apiKey,
                    'anthropic-version: 2023-06-01',
                ],
            ]);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                logger()->error('ClaudeService cURL error: ' . $curlError);
                return null;
            }

            if ($httpCode !== 200) {
                logger()->error("ClaudeService HTTP {$httpCode}: {$response}");
                return null;
            }

            $data = json_decode($response, true);
            $text = $data['content'][0]['text'] ?? null;

            if (empty($text)) {
                logger()->error('ClaudeService: empty content in response — ' . $response);
                return null;
            }

            return trim($text);
        } catch (Throwable $e) {
            logger()->error('ClaudeService exception: ' . $e->getMessage());
            return null;
        }
    }
}
