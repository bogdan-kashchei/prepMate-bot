<?php

declare(strict_types=1);

namespace App\Conversations;

use App\Services\ClaudeService;
use App\Services\QuestionService;
use App\Services\UserService;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

/**
 * FSM Interview Conversation
 *
 * Step flow:
 *
 *   start → handleCategory → handleLevel → handleQuestionChoice
 *                                              │
 *                              ┌───────────────┴────────────────┐
 *                    "Answer yourself"                    "Show answer"
 *                              │                               │
 *                         handleAnswer                  handleSelfGrade
 *                              │                               │
 *                              └───────────┬───────────────────┘
 *                                     handleContinue
 *                                          │
 *                          ┌───────────────┴───────────────┐
 *                    "Next Question"                  "Change Topic"
 *                          │                               │
 *                   (loop: askQuestion)          (loop: start categories)
 */
class InterviewConversation extends Conversation
{
    // Persisted between conversation steps (serialized by Nutgram)
    protected ?string $category       = null;
    protected ?string $level          = null;
    protected ?int    $questionId     = null;
    protected ?string $questionText   = null;
    protected ?string $questionAnswer = null;
    protected ?int    $userId         = null;  // internal DB user id

    // -------------------------------------------------------------------------
    // Step 1 — Choose specialization
    // -------------------------------------------------------------------------

    public function start(Nutgram $bot): void
    {
        $this->resolveUserId($bot);

        $bot->sendMessage(
            text: "🎯 *Choose your specialization:*",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🖥 Frontend', callback_data: 'cat_frontend'))
                ->addRow(InlineKeyboardButton::make('⚙️ Backend',  callback_data: 'cat_backend'))
                ->addRow(InlineKeyboardButton::make('🔍 QA',       callback_data: 'cat_qa'))
                ->addRow(InlineKeyboardButton::make('📊 BA',       callback_data: 'cat_ba')),
        );

        $this->next('handleCategory');
    }

    // -------------------------------------------------------------------------
    // Step 2 — Handle category selection
    // -------------------------------------------------------------------------

    public function handleCategory(Nutgram $bot): void
    {
        if (!$bot->isCallbackQuery()) {
            $bot->sendMessage('Please tap one of the buttons above to choose a specialization.');
            $this->next('handleCategory');
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';

        if (!in_array($data, ['cat_frontend', 'cat_backend', 'cat_qa', 'cat_ba'], true)) {
            $bot->answerCallbackQuery(text: 'Unknown option, please choose again.');
            $this->next('handleCategory');
            return;
        }

        $bot->answerCallbackQuery();
        $this->category = str_replace('cat_', '', $data);

        $labels = ['frontend' => 'Frontend', 'backend' => 'Backend', 'qa' => 'QA', 'ba' => 'BA'];

        $bot->sendMessage(
            text: "✅ Specialization: *{$labels[$this->category]}*\n\n📈 *Choose your level:*",
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('🟢 Junior', callback_data: 'lvl_junior'))
                ->addRow(InlineKeyboardButton::make('🟡 Middle', callback_data: 'lvl_middle'))
                ->addRow(InlineKeyboardButton::make('🔴 Senior', callback_data: 'lvl_senior')),
        );

        $this->next('handleLevel');
    }

    // -------------------------------------------------------------------------
    // Step 3 — Handle level selection, then ask first question
    // -------------------------------------------------------------------------

    public function handleLevel(Nutgram $bot): void
    {
        if (!$bot->isCallbackQuery()) {
            $bot->sendMessage('Please tap one of the buttons to choose your level.');
            $this->next('handleLevel');
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';

        if (!in_array($data, ['lvl_junior', 'lvl_middle', 'lvl_senior'], true)) {
            $bot->answerCallbackQuery(text: 'Unknown option, please choose again.');
            $this->next('handleLevel');
            return;
        }

        $bot->answerCallbackQuery();
        $this->level = str_replace('lvl_', '', $data);

        $this->resolveUserId($bot);

        (new UserService())->saveSession($this->userId, $this->category, $this->level);

        $this->askQuestion($bot);
    }

    // -------------------------------------------------------------------------
    // Step 4 — User chooses: type own answer OR view reference answer
    // -------------------------------------------------------------------------

    public function handleQuestionChoice(Nutgram $bot): void
    {
        if (!$bot->isCallbackQuery()) {
            $bot->sendMessage('Please use the buttons below the question to continue.');
            $this->next('handleQuestionChoice');
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';
        $bot->answerCallbackQuery();

        if ($data === 'q_self_answer') {
            $bot->sendMessage('✏️ Type your answer below:');
            $this->next('handleAnswer');
            return;
        }

        if ($data === 'q_show_answer') {
            $answer = $this->questionAnswer !== '' && $this->questionAnswer !== null
                ? $this->questionAnswer
                : '_No reference answer available for this question._';

            $bot->sendMessage(
                text: "📖 *Reference Answer:*\n\n{$answer}\n\n*Did you know this?*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(
                        InlineKeyboardButton::make('✅ Knew it',       callback_data: 'grade_knew'),
                        InlineKeyboardButton::make("❌ Didn't know",   callback_data: 'grade_didnt_know'),
                    ),
            );

            $this->next('handleSelfGrade');
            return;
        }

        $this->next('handleQuestionChoice');
    }

    // -------------------------------------------------------------------------
    // Step 5A — Receive typed answer, get Claude feedback
    // -------------------------------------------------------------------------

    public function handleAnswer(Nutgram $bot): void
    {
        $this->resolveUserId($bot);

        if ($bot->isCallbackQuery()) {
            $bot->answerCallbackQuery(text: 'Please type your answer as a text message.');
            $this->next('handleAnswer');
            return;
        }

        $answer = trim($bot->message()?->text ?? '');

        if ($answer === '') {
            $bot->sendMessage('Please type your answer as a text message.');
            $this->next('handleAnswer');
            return;
        }

        $bot->sendMessage('⏳ Analyzing your answer, please wait...');

        $feedback = (new ClaudeService())->getFeedback($this->questionText ?? '', $answer);

        if ($feedback === null) {
            $feedback = '⚠️ Feedback is temporarily unavailable. Please try again later.';
        }

        try {
            (new QuestionService())->saveAnswer(
                userId:     $this->userId,
                questionId: $this->questionId,
                answerText: $answer,
                aiFeedback: $feedback,
                selfGrade:  null,
            );
        } catch (\Throwable $e) {
            logger()->error('handleAnswer saveAnswer: ' . $e->getMessage());
        }

        $bot->sendMessage(
            text: "🤖 *Feedback:*\n\n{$feedback}",
            parse_mode: 'Markdown',
        );

        $this->showContinueButtons($bot);
    }

    // -------------------------------------------------------------------------
    // Step 5B — User self-grades after viewing reference answer
    // -------------------------------------------------------------------------

    public function handleSelfGrade(Nutgram $bot): void
    {
        $this->resolveUserId($bot);

        if (!$bot->isCallbackQuery()) {
            $bot->sendMessage('Please use the buttons to grade yourself.');
            $this->next('handleSelfGrade');
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';

        if (!in_array($data, ['grade_knew', 'grade_didnt_know'], true)) {
            $bot->answerCallbackQuery();
            $this->next('handleSelfGrade');
            return;
        }

        $bot->answerCallbackQuery();

        $selfGrade = $data === 'grade_knew' ? 'knew' : 'didnt_know';
        $emoji     = $selfGrade === 'knew' ? '✅' : '❌';

        try {
            (new QuestionService())->saveAnswer(
                userId:     $this->userId,
                questionId: $this->questionId,
                answerText: null,
                aiFeedback: null,
                selfGrade:  $selfGrade,
            );
        } catch (\Throwable $e) {
            logger()->error('handleSelfGrade saveAnswer: ' . $e->getMessage());
        }

        $bot->sendMessage("Saved {$emoji}");

        $this->showContinueButtons($bot);
    }

    // -------------------------------------------------------------------------
    // Step 6 — Next question or change topic
    // -------------------------------------------------------------------------

    public function handleContinue(Nutgram $bot): void
    {
        if (!$bot->isCallbackQuery()) {
            $bot->sendMessage('Please use the buttons above to continue.');
            $this->next('handleContinue');
            return;
        }

        $data = $bot->callbackQuery()?->data ?? '';
        $bot->answerCallbackQuery();

        if ($data === 'action_next') {
            $this->askQuestion($bot);
            return;
        }

        if ($data === 'action_change') {
            $this->category       = null;
            $this->level          = null;
            $this->questionId     = null;
            $this->questionText   = null;
            $this->questionAnswer = null;

            $bot->sendMessage(
                text: "🎯 *Choose your specialization:*",
                parse_mode: 'Markdown',
                reply_markup: InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('🖥 Frontend', callback_data: 'cat_frontend'))
                    ->addRow(InlineKeyboardButton::make('⚙️ Backend',  callback_data: 'cat_backend'))
                    ->addRow(InlineKeyboardButton::make('🔍 QA',       callback_data: 'cat_qa'))
                    ->addRow(InlineKeyboardButton::make('📊 BA',       callback_data: 'cat_ba')),
            );

            $this->next('handleCategory');
            return;
        }

        $this->next('handleContinue');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function askQuestion(Nutgram $bot): void
    {
        $this->resolveUserId($bot);

        $question = (new QuestionService())->getRandomQuestion(
            category: $this->category,
            level:    $this->level,
            userId:   $this->userId,
        );

        if ($question === null) {
            $bot->sendMessage('No questions available for this combination. Please choose a different topic.');
            $this->end();
            return;
        }

        $this->questionId     = (int) $question['id'];
        $this->questionText   = $question['question_text'];
        $this->questionAnswer = $question['answer'] ?? '';

        $levelLabel    = ucfirst($this->level ?? '');
        $categoryLabel = strtoupper($this->category ?? '');

        $text = "💬 *[{$categoryLabel} — {$levelLabel}]*\n\n{$this->questionText}";

        $hints = $question['hints'] ?? [];
        if (!empty($hints)) {
            $hintList = implode("\n", array_map(fn($h) => "• {$h}", $hints));
            $text .= "\n\n💡 *Hints:*\n{$hintList}";
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: 'Markdown',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✍️ Answer Yourself', callback_data: 'q_self_answer'),
                    InlineKeyboardButton::make('👁 Show Answer',      callback_data: 'q_show_answer'),
                ),
        );

        $this->next('handleQuestionChoice');
    }

    private function resolveUserId(Nutgram $bot): void
    {
        if ($this->userId !== null) {
            return;
        }

        $telegramId = $bot->userId();
        if ($telegramId === null) {
            return;
        }

        $userService  = new UserService();
        $this->userId = $userService->getIdByTelegramId($telegramId);

        if ($this->userId === null) {
            $this->userId = $userService->createOrUpdate(
                telegramId:   $telegramId,
                username:     $bot->user()?->username      ?? '',
                firstName:    $bot->user()?->first_name    ?? '',
                languageCode: $bot->user()?->language_code ?? '',
            );
        } else {
            $userService->touchLastSeen($this->userId);
        }
    }

    private function showContinueButtons(Nutgram $bot): void
    {
        $bot->sendMessage(
            text: 'What would you like to do next?',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('➡️ Next Question', callback_data: 'action_next'))
                ->addRow(InlineKeyboardButton::make('🔄 Change Topic',  callback_data: 'action_change')),
        );

        $this->next('handleContinue');
    }
}
