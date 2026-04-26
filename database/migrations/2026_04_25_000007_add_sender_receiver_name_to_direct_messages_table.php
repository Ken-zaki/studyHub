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
        Schema::table('direct_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('direct_messages', 'sender_name')) {
                $table->string('sender_name', 191)->nullable()->after('sender_id');
            }

            if (!Schema::hasColumn('direct_messages', 'receiver_name')) {
                $table->string('receiver_name', 191)->nullable()->after('receiver_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            if (Schema::hasColumn('direct_messages', 'sender_name')) {
                $table->dropColumn('sender_name');
            }

            if (Schema::hasColumn('direct_messages', 'receiver_name')) {
                $table->dropColumn('receiver_name');
            }
        });
    }
};
