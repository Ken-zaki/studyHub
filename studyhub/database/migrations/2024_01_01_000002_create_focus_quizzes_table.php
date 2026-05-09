<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_quizzes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');          // matches session('user_id') — Supabase UUID stored as string
            $table->string('question', 500);
            $table->string('option_a', 400);
            $table->string('option_b', 400);
            $table->string('option_c', 400);
            $table->string('option_d', 400);
            $table->char('correct_option', 1);  // 'A' | 'B' | 'C' | 'D'
            $table->string('explanation', 1000)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_quizzes');
    }
};
