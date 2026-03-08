<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('category');  // frontend | backend | qa | ba
            $table->string('level');     // junior | middle | senior
            $table->text('question_text');
            $table->text('answer')->default('');
            $table->json('hints')->default('[]');
            $table->timestamps();

            $table->index(['category', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
