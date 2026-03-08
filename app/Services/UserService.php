<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramUser;
use App\Models\UserAnswer;
use App\Models\UserSession;
use App\Models\Question;
use Throwable;

class UserService
{
    /**
     * Insert or update a user by telegram_id. Returns the internal DB id.
     */
    public function createOrUpdate(int $telegramId, string $username, string $firstName, string $languageCode = ''): int
    {
        try {
            $user = TelegramUser::updateOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'username'      => $username,
                    'first_name'    => $firstName,
                    'language_code' => $languageCode,
                    'last_seen_at'  => now(),
                ],
            );

            return $user->id;
        } catch (Throwable $e) {
            logger()->error('UserService::createOrUpdate — ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Returns the internal user id for a given telegram_id, or null if not found.
     */
    public function getIdByTelegramId(int $telegramId): ?int
    {
        return TelegramUser::where('telegram_id', $telegramId)->value('id');
    }

    public function touchLastSeen(int $userId): void
    {
        TelegramUser::where('id', $userId)->update(['last_seen_at' => now()]);
    }

    /**
     * Save (upsert) the user's current interview session.
     */
    public function saveSession(int $userId, string $category, string $level): void
    {
        try {
            UserSession::updateOrCreate(
                ['user_id' => $userId],
                ['category' => $category, 'level' => $level],
            );
        } catch (Throwable $e) {
            logger()->error('UserService::saveSession — ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Returns detailed progress stats for the user.
     */
    public function getProgress(int $userId): array
    {
        try {
            $answers = UserAnswer::where('user_id', $userId)->get();

            $selfAnswered = $answers->whereNull('self_grade')->count();
            $viewedKnew   = $answers->where('self_grade', 'knew')->count();
            $viewedDidnt  = $answers->where('self_grade', 'didnt_know')->count();

            $byCategory = [];
            $categories = $answers->pluck('question_id')->unique()->toArray();

            if (!empty($categories)) {
                $questionCategories = Question::whereIn('id', $categories)
                    ->pluck('category', 'id')
                    ->toArray();

                foreach ($answers as $answer) {
                    $cat = $questionCategories[$answer->question_id] ?? null;
                    if ($cat === null) {
                        continue;
                    }

                    if (!isset($byCategory[$cat])) {
                        $byCategory[$cat] = ['self_answered' => 0, 'viewed_knew' => 0, 'viewed_didnt' => 0];
                    }

                    if ($answer->self_grade === null) {
                        $byCategory[$cat]['self_answered']++;
                    } elseif ($answer->self_grade === 'knew') {
                        $byCategory[$cat]['viewed_knew']++;
                    } else {
                        $byCategory[$cat]['viewed_didnt']++;
                    }
                }

                foreach ($byCategory as $cat => &$data) {
                    $total = $data['viewed_knew'] + $data['viewed_didnt'];
                    $data['knew_pct'] = $total > 0
                        ? (int) round($data['viewed_knew'] / $total * 100)
                        : null;
                }
            }

            return [
                'self_answered' => $selfAnswered,
                'viewed_knew'   => $viewedKnew,
                'viewed_didnt'  => $viewedDidnt,
                'by_category'   => $byCategory,
            ];
        } catch (Throwable $e) {
            logger()->error('UserService::getProgress — ' . $e->getMessage());
            return ['self_answered' => 0, 'viewed_knew' => 0, 'viewed_didnt' => 0, 'by_category' => []];
        }
    }
}
