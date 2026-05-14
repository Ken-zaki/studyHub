<?php
// ─────────────────────────────────────────────────────────────
//  database/migrations/2024_01_01_000002_create_focus_materials_and_notes_tables.php
//
//  ACTION REQUIRED:
//  1. DELETE the old migration file:
//       database/migrations/2024_01_01_000001_create_study_material_notes_table.php
//     (it referenced a non-existent `materials` table and will break `php artisan migrate`)
//
//  2. If you already ran migrations, roll back first:
//       php artisan migrate:rollback
//     Then run again:
//       php artisan migrate
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── focus_materials ──────────────────────────────────
        // Mirrors what storeMaterial() writes to the session,
        // kept as a persistent record (optional — notes need the ID).
        if (!Schema::hasTable('focus_materials')) {
            Schema::create('focus_materials', function (Blueprint $table) {
                $table->string('id')->primary();            // UUID from Str::uuid()
                $table->string('user_id');                  // session-based user_id (not an int FK)
                $table->string('screen')->default('screenReview');
                $table->string('name');                     // original filename
                $table->string('file_path');                // relative path under public/
                $table->string('type', 10);                 // pdf, docx, pptx, etc.
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // ── focus_material_notes ─────────────────────────────
        // One row per (user, material). Upserted by saveNote().
        if (!Schema::hasTable('focus_material_notes')) {
            Schema::create('focus_material_notes', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');                  // session-based, plain string
                $table->string('study_material_id');        // UUID → focus_materials.id
                $table->longText('content')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'study_material_id']);
                $table->index('study_material_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_material_notes');
        Schema::dropIfExists('focus_materials');
    }
};