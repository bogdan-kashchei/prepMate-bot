<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('telegram_users')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->text('ai_feedback')->nullable();
            $table->string('self_grade')->nullable(); // 'knew' | 'didnt_know' | null
            $table->timestamps();

            $table->index(['user_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
