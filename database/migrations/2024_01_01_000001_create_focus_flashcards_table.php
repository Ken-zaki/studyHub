<?php
// ─────────────────────────────────────────────────────────────
//  Migration: create focus_flashcard_decks table
//             + add deck_id column to focus_flashcards
//
//  Run with: php artisan migrate
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create the decks table ──────────────────────────
        Schema::create('focus_flashcard_decks', function (Blueprint $table) {
            $table->string('id', 36)->primary();   // UUID, matches your existing pattern
            $table->string('user_id', 36)->index();
            $table->string('name', 120);
            $table->string('description', 250)->default('');
            $table->timestamps();
        });

        // ── 2. Add deck_id to the existing focus_flashcards table ──
        //    Only runs if the column doesn't already exist, so it's
        //    safe to re-run or deploy on top of an existing install.
        if (Schema::hasTable('focus_flashcards') && !Schema::hasColumn('focus_flashcards', 'deck_id')) {
            Schema::table('focus_flashcards', function (Blueprint $table) {
                // nullable so any existing cards don't break
                $table->string('deck_id', 36)->nullable()->after('user_id')->index();
            });
        }
    }

    public function down(): void
    {
        // Remove deck_id from flashcards first
        if (Schema::hasTable('focus_flashcards') && Schema::hasColumn('focus_flashcards', 'deck_id')) {
            Schema::table('focus_flashcards', function (Blueprint $table) {
                $table->dropColumn('deck_id');
            });
        }

        Schema::dropIfExists('focus_flashcard_decks');
    }
};