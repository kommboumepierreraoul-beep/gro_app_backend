<?php
// database/migrations/2024_01_01_000003_create_moderation_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('moderatable');  // Polymorphique : Post, Comment, etc.
            $table->boolean('is_safe')->default(true);
            $table->float('score')->default(0.0);
            $table->json('categories')->nullable();
            $table->text('reason')->nullable();
            $table->enum('action', ['approved', 'flagged', 'rejected'])->default('approved');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
