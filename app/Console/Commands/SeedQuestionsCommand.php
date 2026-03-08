<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SeedQuestionsCommand extends Command
{
    protected $signature   = 'bot:seed-questions {--fresh : Delete all questions before seeding}';
    protected $description = 'Load questions from questions/*.json into the database';

    private const CATEGORIES = ['frontend', 'backend', 'qa', 'ba'];
    private const LEVELS     = ['junior', 'middle', 'senior'];

    public function handle(): int
    {
        $questionsDir = base_path('questions');

        if (!File::isDirectory($questionsDir)) {
            $this->error("Questions directory not found: {$questionsDir}");
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Question::truncate();
            $this->info('Existing questions deleted.');
        }

        $total = 0;

        foreach (self::CATEGORIES as $category) {
            $file = "{$questionsDir}/{$category}.json";

            if (!File::exists($file)) {
                $this->warn("Seed file not found: {$file}");
                continue;
            }

            $data = json_decode(File::get($file), true);

            if (!is_array($data)) {
                $this->error("Invalid JSON in: {$file}");
                continue;
            }

            foreach (self::LEVELS as $level) {
                if (!isset($data[$level]) || !is_array($data[$level])) {
                    continue;
                }

                foreach ($data[$level] as $item) {
                    if (empty($item['question'])) {
                        continue;
                    }

                    Question::firstOrCreate(
                        [
                            'category'      => $category,
                            'level'         => $level,
                            'question_text' => $item['question'],
                        ],
                        [
                            'answer' => $item['answer'] ?? '',
                            'hints'  => $item['hints'] ?? [],
                        ]
                    );

                    $total++;
                }
            }

            $this->line("  ✓ {$category}");
        }

        $this->info("Done. {$total} questions processed.");

        return self::SUCCESS;
    }
}
