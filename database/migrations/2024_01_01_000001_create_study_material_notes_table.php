<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_material_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('study_material_id')->constrained('materials')->onDelete('cascade');
            $table->longText('content')->nullable();
            $table->timestamps();

            // One note record per user per material
            $table->unique(['user_id', 'study_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_material_notes');
    }
};