<?php

declare(strict_types=1);

namespace Tests\Unit\Conversations;

use App\Conversations\InterviewConversation;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class InterviewConversationTest extends TestCase
{
    private function makeConversation(): InterviewConversation
    {
        return (new ReflectionClass(InterviewConversation::class))
            ->newInstanceWithoutConstructor();
    }

    private function setProps(InterviewConversation $conv, array $props): void
    {
        $ref = new ReflectionClass($conv);

        foreach ($props as $name => $value) {
            $prop = $ref->getProperty($name);
            $prop->setValue($conv, $value);
        }
    }

    private function callPrivate(InterviewConversation $conv, string $method, mixed ...$args): mixed
    {
        $ref = new ReflectionClass($conv);
        $m   = $ref->getMethod($method);
        return $m->invoke($conv, ...$args);
    }

    // -------------------------------------------------------------------------
    // buildQuestionText — no tips
    // -------------------------------------------------------------------------

    public function test_build_question_text_contains_category_and_level(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, [
            'category'     => 'backend',
            'level'        => 'junior',
            'questionText' => 'What is a closure?',
        ]);

        $text = $this->callPrivate($conv, 'buildQuestionText', false);

        $this->assertStringContainsString('BACKEND', $text);
        $this->assertStringContainsString('Junior', $text);
        $this->assertStringContainsString('What is a closure?', $text);
    }

    public function test_build_question_text_without_tips_omits_hints_section(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, [
            'category'      => 'frontend',
            'level'         => 'middle',
            'questionText'  => 'Explain the virtual DOM.',
            'questionHints' => json_encode(['Think about diffing', 'Reconciliation']),
        ]);

        $text = $this->callPrivate($conv, 'buildQuestionText', false);

        $this->assertStringNotContainsString('Tips', $text);
        $this->assertStringNotContainsString('Think about diffing', $text);
    }

    public function test_build_question_text_with_tips_includes_hints_section(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, [
            'category'      => 'frontend',
            'level'         => 'middle',
            'questionText'  => 'Explain the virtual DOM.',
            'questionHints' => json_encode(['Think about diffing', 'Reconciliation']),
        ]);

        $text = $this->callPrivate($conv, 'buildQuestionText', true);

        $this->assertStringContainsString('Tips', $text);
        $this->assertStringContainsString('Think about diffing', $text);
        $this->assertStringContainsString('Reconciliation', $text);
    }

    public function test_build_question_text_with_tips_true_but_no_hints_omits_hints_section(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, [
            'category'      => 'qa',
            'level'         => 'senior',
            'questionText'  => 'What is exploratory testing?',
            'questionHints' => null,
        ]);

        $text = $this->callPrivate($conv, 'buildQuestionText', true);

        $this->assertStringNotContainsString('Tips', $text);
    }

    public function test_build_question_text_hints_are_bulleted(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, [
            'category'      => 'backend',
            'level'         => 'senior',
            'questionText'  => 'Describe CAP theorem.',
            'questionHints' => json_encode(['Consistency', 'Availability']),
        ]);

        $text = $this->callPrivate($conv, 'buildQuestionText', true);

        $this->assertStringContainsString('• Consistency', $text);
        $this->assertStringContainsString('• Availability', $text);
    }

    // -------------------------------------------------------------------------
    // buildQuestionKeyboard — tips button presence and labels
    // -------------------------------------------------------------------------

    public function test_keyboard_has_no_tips_button_when_tips_visible_is_null(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => null]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $this->assertInstanceOf(InlineKeyboardMarkup::class, $keyboard);

        $allCallbackData = $this->collectCallbackData($keyboard);
        $this->assertNotContains('q_show_tips', $allCallbackData);
        $this->assertNotContains('q_hide_tips', $allCallbackData);
    }

    public function test_keyboard_has_show_tips_button_when_tips_are_hidden(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => false]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $allCallbackData = $this->collectCallbackData($keyboard);
        $this->assertContains('q_show_tips', $allCallbackData);
        $this->assertNotContains('q_hide_tips', $allCallbackData);
    }

    public function test_keyboard_has_hide_tips_button_when_tips_are_visible(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => true]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $allCallbackData = $this->collectCallbackData($keyboard);
        $this->assertContains('q_hide_tips', $allCallbackData);
        $this->assertNotContains('q_show_tips', $allCallbackData);
    }

    public function test_keyboard_always_has_self_answer_and_show_answer_buttons(): void
    {
        foreach ([null, false, true] as $tipsVisible) {
            $conv = $this->makeConversation();
            $this->setProps($conv, ['tipsVisible' => $tipsVisible]);

            /** @var InlineKeyboardMarkup $keyboard */
            $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

            $allCallbackData = $this->collectCallbackData($keyboard);
            $this->assertContains('q_self_answer', $allCallbackData, "tipsVisible={$this->describe($tipsVisible)}");
            $this->assertContains('q_show_answer', $allCallbackData, "tipsVisible={$this->describe($tipsVisible)}");
        }
    }

    public function test_keyboard_has_one_row_when_no_hints_exist(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => null]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $this->assertCount(1, $keyboard->inline_keyboard);
    }

    public function test_keyboard_has_two_rows_when_hints_exist(): void
    {
        foreach ([false, true] as $tipsVisible) {
            $conv = $this->makeConversation();
            $this->setProps($conv, ['tipsVisible' => $tipsVisible]);

            /** @var InlineKeyboardMarkup $keyboard */
            $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

            $this->assertCount(2, $keyboard->inline_keyboard, "tipsVisible={$this->describe($tipsVisible)}");
        }
    }

    public function test_tips_button_is_in_first_row(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => false]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $firstRowCallbacks = array_map(
            fn($btn) => $btn->callback_data,
            $keyboard->inline_keyboard[0],
        );

        $this->assertContains('q_show_tips', $firstRowCallbacks);
    }

    public function test_action_buttons_are_in_last_row(): void
    {
        $conv = $this->makeConversation();
        $this->setProps($conv, ['tipsVisible' => false]);

        /** @var InlineKeyboardMarkup $keyboard */
        $keyboard = $this->callPrivate($conv, 'buildQuestionKeyboard');

        $lastRow = end($keyboard->inline_keyboard);
        $lastRowCallbacks = array_map(fn($btn) => $btn->callback_data, $lastRow);

        $this->assertContains('q_self_answer', $lastRowCallbacks);
        $this->assertContains('q_show_answer', $lastRowCallbacks);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return string[] */
    private function collectCallbackData(InlineKeyboardMarkup $keyboard): array
    {
        $data = [];
        foreach ($keyboard->inline_keyboard as $row) {
            foreach ($row as $button) {
                if ($button->callback_data !== null) {
                    $data[] = $button->callback_data;
                }
            }
        }
        return $data;
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            $value === null  => 'null',
            $value === true  => 'true',
            $value === false => 'false',
            default          => (string) $value,
        };
    }
}
