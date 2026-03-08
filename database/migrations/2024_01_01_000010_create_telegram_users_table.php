<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('telegram_id')->unique();
            $table->string('username')->default('');
            $table->string('first_name')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_users');
    }
};
