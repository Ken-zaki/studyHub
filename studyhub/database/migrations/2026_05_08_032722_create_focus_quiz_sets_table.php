<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Quiz sets table ────────────────────────────────────────
        Schema::create('focus_quiz_sets', function (Blueprint $table) {
            $table->string('id')->primary();          // UUID set by the controller
            $table->string('user_id');
            $table->string('title');
            $table->string('description')->default('');
            $table->timestamps();
            $table->index('user_id');
        });

        // ── 2. Add quiz_set_id to existing focus_quizzes table ────────
        Schema::table('focus_quizzes', function (Blueprint $table) {
            // nullable so existing rows (created before sets existed) keep working
            $table->string('quiz_set_id')->nullable()->after('user_id');
            $table->index('quiz_set_id');
        });
    }

    public function down(): void
    {
        Schema::table('focus_quizzes', function (Blueprint $table) {
            $table->dropIndex(['quiz_set_id']);
            $table->dropColumn('quiz_set_id');
        });

        Schema::dropIfExists('focus_quiz_sets');
    }
};
