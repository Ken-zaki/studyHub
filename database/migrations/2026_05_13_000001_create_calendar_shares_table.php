<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');      // User sharing their calendar
            $table->uuid('recipient_id');  // User receiving the shared calendar
            $table->enum('status', ['active', 'paused', 'revoked'])->default('active');
            $table->boolean('can_see_details')->default(true); // Can see event details or just busy/free
            $table->timestamps();

            $table->unique(['owner_id', 'recipient_id']);
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_shares');
    }
};
