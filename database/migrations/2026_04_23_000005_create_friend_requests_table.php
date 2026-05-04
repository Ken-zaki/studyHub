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
        Schema::create('friend_requests', function (Blueprint $table) {
            $table->id();
            $table->string('sender_id');
            $table->string('receiver_id');
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->timestamps();

            // Add foreign key constraints if users table exists
            // $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            // $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');

            // Add unique constraint to prevent duplicate requests
            $table->unique(['sender_id', 'receiver_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_requests');
    }
};
