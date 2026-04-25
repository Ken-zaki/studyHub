<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('focus_sessions')) {
            return;
        }

        $column = DB::selectOne(
            "SELECT DATA_TYPE, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'focus_sessions'
               AND COLUMN_NAME = 'user_id'"
        );

        if ($column && str_contains(strtolower($column->COLUMN_TYPE), 'varchar')) {
            return;
        }

        $foreignKey = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'focus_sessions'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        if ($foreignKey && isset($foreignKey->CONSTRAINT_NAME)) {
            DB::statement(
                'ALTER TABLE focus_sessions DROP FOREIGN KEY `' . $foreignKey->CONSTRAINT_NAME . '`'
            );
        }

        DB::statement('ALTER TABLE focus_sessions MODIFY user_id VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('focus_sessions')) {
            return;
        }

        $column = DB::selectOne(
            "SELECT DATA_TYPE, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'focus_sessions'
               AND COLUMN_NAME = 'user_id'"
        );

        if ($column && str_contains(strtolower($column->COLUMN_TYPE), 'bigint')) {
            return;
        }

        DB::statement('ALTER TABLE focus_sessions MODIFY user_id BIGINT UNSIGNED NOT NULL');

        DB::statement(
            'ALTER TABLE focus_sessions ADD CONSTRAINT focus_sessions_user_id_foreign '
            . 'FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE'
        );
    }
};