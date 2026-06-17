<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            // Relation polymorphe : fonctionne sur Post, Comment, ou n'importe quel modèle
            $table->morphs('moderatable'); // moderatable_type + moderatable_id + index
            $table->string('content_hash', 64); // SHA-256 pour le cache Redis
            $table->boolean('flagged')->default(false);
            $table->float('confidence_score')->nullable(); // 0.0 - 1.0
            $table->json('reasons')->nullable(); // ['spam', 'hate_speech', ...]
            $table->json('raw_response')->nullable(); // réponse brute de l'API IA
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('model_used')->nullable();
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->timestamps();

            $table->index(['moderatable_type', 'moderatable_id', 'created_at']);
            $table->index('flagged');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
