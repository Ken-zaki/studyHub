<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendRequestsAndFriendsTables extends Migration
{
    public function up(): void
    {
        Schema::create('friend_requests', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');
            $table->string('receiver_id');
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'status']);
            $table->index(['sender_id', 'status']);
        });

        Schema::create('friends', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('friend_id');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'friend_id']);
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
