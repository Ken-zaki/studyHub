<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcard_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add deck_id to existing flashcards table
        // Only add if the column doesn't exist yet
        if (Schema::hasTable('flashcards') && !Schema::hasColumn('flashcards', 'deck_id')) {
            Schema::table('flashcards', function (Blueprint $table) {
                $table->foreignId('deck_id')
                      ->nullable()
                      ->after('user_id')
                      ->constrained('flashcard_decks')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('flashcards') && Schema::hasColumn('flashcards', 'deck_id')) {
            Schema::table('flashcards', function (Blueprint $table) {
                $table->dropForeign(['deck_id']);
                $table->dropColumn('deck_id');
            });
        }
        Schema::dropIfExists('flashcard_decks');
    }
};
