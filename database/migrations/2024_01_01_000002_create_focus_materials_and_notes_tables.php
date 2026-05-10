<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_materials', function (Blueprint $table) {
            $table->string('id')->primary();        // UUID
            $table->string('user_id');              // matches your session-based user_id pattern
            $table->string('screen')->default('screenReview'); // which focus screen it belongs to
            $table->string('name');                 // original filename
            $table->string('file_path');            // relative path under public/
            $table->string('type', 10);             // pdf, docx, pptx, etc.
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('focus_material_notes', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('study_material_id');   // UUID → focus_materials.id
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'study_material_id']);
            $table->index('study_material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('focus_material_notes');
        Schema::dropIfExists('focus_materials');
    }
};