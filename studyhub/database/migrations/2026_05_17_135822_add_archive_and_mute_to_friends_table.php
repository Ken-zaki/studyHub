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
         Schema::table('friends', function (Blueprint $table) {

            $table->boolean('is_muted')->default(false);

            $table->boolean('is_archived')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('friends', function (Blueprint $table) {

            $table->dropColumn(['is_muted', 'is_archived']);

        });
    }
};
