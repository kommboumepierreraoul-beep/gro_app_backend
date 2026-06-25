<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('content_type', [
                'post',
                'comment',
                'message',
                'user'
            ]);

            $table->unsignedBigInteger('content_id');

            $table->enum('reason', [
                'spam',
                'harassment',
                'hate_speech',
                'violence',
                'inappropriate',
                'misinformation',
                'other'
            ]);

            $table->text('description')->nullable();

            $table->enum('status', [
                'pending',
                'reviewing',
                'resolved',
                'dismissed'
            ])->default('pending');

            $table->foreignId('resolved_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // Index
            $table->index(['content_type', 'content_id']);
            $table->index('status');
            $table->index('reason');
            $table->index(['reporter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_reports');
    }
};
