<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToFriendRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::table('friend_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('friend_requests', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrent();
            }
            if (!Schema::hasColumn('friend_requests', 'responded_at')) {
                $table->timestamp('responded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('friend_requests', function (Blueprint $table) {
            if (Schema::hasColumn('friend_requests', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
            if (Schema::hasColumn('friend_requests', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
}
