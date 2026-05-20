<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->string('file_name');
            $table->text('file_url');
            $table->bigInteger('file_size')->nullable();
            $table->string('attachment_type')->default('file'); // 'image' or 'file'
            $table->text('storage_path')->nullable();
            $table->timestamps();

            $table->foreign('message_id')
                  ->references('id')
                  ->on('group_messages')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_message_attachments');
    }
};