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
        Schema::create('friends', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('friend_id');
            $table->timestamps();

            // Add foreign key constraints if users table exists
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('friend_id')->references('id')->on('users')->onDelete('cascade');

            // Add unique constraint to prevent duplicate friendships
            $table->unique(['user_id', 'friend_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friends');
    }
};
