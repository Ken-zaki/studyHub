<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendRequestsAndFriendsTables extends Migration
{
    public function up(): void
    {
        Schema::create('friend_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sender_id')->nullable();
            $table->uuid('receiver_id')->nullable();
            $table->text('status')->default('pending');
            $table->timestamp('created_at')->useCurrent();

            // Add indexes for faster queries
            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('status');
        });

        Schema::create('friends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('friend_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Add indexes for faster queries
            $table->index('user_id');
            $table->index('friend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friends');
        Schema::dropIfExists('friend_requests');
    }
}


