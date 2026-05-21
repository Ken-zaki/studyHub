<?php
// ─────────────────────────────────────────────────────────────
//  Migration: create base focus_flashcards table
//
//  IMPORTANT: This file must be named so it sorts BEFORE
//  2024_01_01_000001_create_focus_flashcard_decks_table.php
//  so that the table exists when that migration tries to
//  add deck_id to it.
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
        if (Schema::hasTable('focus_flashcards')) {
            return; // already exists, nothing to do
        }

        Schema::create('focus_flashcards', function (Blueprint $table) {
            $table->string('id', 36)->primary();        // UUID
            $table->string('user_id', 36)->index();
            $table->string('deck_id', 36)->nullable()->index(); // added here so 000001 can skip it
            $table->text('question');
            $table->text('answer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_flashcards');
    }
};
