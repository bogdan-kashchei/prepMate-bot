<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class QuestionSeeder extends Seeder
{
    private const CATEGORIES = ['frontend', 'backend', 'qa', 'ba'];
    private const LEVELS = ['junior', 'middle', 'senior'];

    public function run(): void
    {
        $questionsDir = base_path('questions');

        if (!File::isDirectory($questionsDir)) {
            $this->command->error("Questions directory not found: {$questionsDir}");
            return;
        }

        $total = 0;

        foreach (self::CATEGORIES as $category) {
            $file = "{$questionsDir}/{$category}.json";

            if (!File::exists($file)) {
                $this->command->warn("Seed file not found: {$file}");
                continue;
            }

            $data = json_decode(File::get($file), true);

            if (!is_array($data)) {
                $this->command->error("Invalid JSON in: {$file}");
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
                            'category'     => $category,
                            'level'        => $level,
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

            $this->command->info("Seeded category: {$category}");
        }

        $this->command->info("Total questions seeded: {$total}");
    }
}
