<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si la table existe déjà
        if (!Schema::hasTable('moderation_audit_log')) {
            Schema::create('moderation_audit_log', function (Blueprint $table) {
                $table->id();

                // Utiliser un nom d'index personnalisé pour éviter les conflits
                $table->morphs('moderatable', 'audit_log_moderatable_idx');

                $table->enum('action', [
                    'pending',
                    'approved',
                    'review',
                    'rejected'
                ]);

                $table->enum('actor_type', [
                    'ai',
                    'moderator',
                    'system'
                ]);

                $table->foreignId('actor_id')->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->json('payload')->nullable();

                $table->timestamp('created_at')->nullable(false)->useCurrent();

                // Index avec noms uniques
                $table->index('action', 'audit_log_action_idx');
                $table->index('created_at', 'audit_log_created_at_idx');
                $table->index(['actor_type', 'actor_id'], 'audit_log_actor_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_audit_log');
    }
};
