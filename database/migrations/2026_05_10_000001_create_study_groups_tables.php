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
        Schema::create('study_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('study_group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('group_id');
            $table->string('user_id')->nullable();
            $table->string('role')->default('member');
            
            $table->unique(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_group_members');
        Schema::dropIfExists('study_groups');
    }
};
