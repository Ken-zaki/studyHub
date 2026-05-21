<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('group_messages', 'updated_at')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('group_messages', 'updated_at')) {
            Schema::table('group_messages', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
