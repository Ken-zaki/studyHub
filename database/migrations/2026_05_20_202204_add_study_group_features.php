<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add study group feature tables
 *
 * Run: php artisan migrate
 *
 * New tables:
 *   - group_message_replies  (threaded replies / GChat-style threads)
 *   - group_tasks            (shared task list)
 *   - group_resources        (pinnable file sharing)
 *   - group_notes            (shared co-edit notes)
 *
 * Alter:
 *   - group_messages: add reply_count column (denormalised, for fast display)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Threaded replies ──────────────────────────────────────
        Schema::create('group_message_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');   // FK → group_messages.id
            $table->uuid('group_id');     // FK → study_groups.id
            $table->string('user_id');    // Supabase UUID (string, not FK)
            $table->text('message');
            $table->timestamps();

            $table->index('message_id');
            $table->index('group_id');
            $table->foreign('message_id')
                  ->references('id')->on('group_messages')
                  ->onDelete('cascade');
        });

        // Add reply_count to parent messages table if column doesn't exist
        if (!Schema::hasColumn('group_messages', 'reply_count')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->unsignedInteger('reply_count')->default(0)->after('message');
            });
        }

        // ── 2. Group tasks ───────────────────────────────────────────
        Schema::create('group_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->string('created_by');           // Supabase user UUID
            $table->string('title');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->date('due_date')->nullable();
            $table->string('assigned_to')->nullable(); // display name or user UUID
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->index('group_id');
            $table->foreign('group_id')
                  ->references('id')->on('study_groups')
                  ->onDelete('cascade');
        });

        // ── 3. Group resources (shared file library + pinning) ───────
        Schema::create('group_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->string('uploaded_by');          // Supabase user UUID
            $table->string('file_name');
            $table->string('file_url');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('attachment_type')->default('file'); // 'image' | 'file'
            $table->string('storage_path')->nullable();
            $table->boolean('pinned')->default(false);
            $table->timestamps();

            $table->index(['group_id', 'pinned']);
            $table->foreign('group_id')
                  ->references('id')->on('study_groups')
                  ->onDelete('cascade');
        });

        // ── 4. Group notes (shared co-edit workspace) ─────────────────
        Schema::create('group_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id');
            $table->string('created_by');           // Supabase user UUID
            $table->string('last_edited_by')->nullable();
            $table->string('title')->default('Untitled Note');
            $table->longText('content')->nullable(); // stores HTML from contenteditable
            $table->timestamps();

            $table->index('group_id');
            $table->foreign('group_id')
                  ->references('id')->on('study_groups')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_notes');
        Schema::dropIfExists('group_resources');
        Schema::dropIfExists('group_tasks');
        Schema::dropIfExists('group_message_replies');

        if (Schema::hasColumn('group_messages', 'reply_count')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('reply_count');
            });
        }
    }
};
