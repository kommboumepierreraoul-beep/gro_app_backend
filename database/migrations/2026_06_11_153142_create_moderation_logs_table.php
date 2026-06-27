<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vérifier si les tables existent déjà
        $exists = Schema::hasTable('moderation_posts');

        if (!$exists) {
            // 1. Table de modération pour les posts
            Schema::create('moderation_posts', function (Blueprint $table) {
                $table->id();

                $table->foreignId('post_id')
                    ->constrained('posts')
                    ->cascadeOnDelete()
                    ->unique('moderation_posts_post_id_unique');

                $table->enum('status', [
                    'pending',
                    'approved',
                    'review',
                    'rejected'
                ])->default('pending');

                $table->float('toxicity_score')->nullable();
                $table->float('spam_score')->nullable();
                $table->float('hate_score')->nullable();
                $table->float('violence_score')->nullable();

                $table->json('result_raw')->nullable();
                $table->text('reason')->nullable();

                $table->string('content_hash', 64)->nullable()->index('moderation_posts_content_hash_index');

                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();

                // Index avec noms explicites
                $table->index('status', 'moderation_posts_status_index');
                $table->index(['post_id', 'status'], 'moderation_posts_post_id_status_index');
                $table->index(['status', 'created_at'], 'moderation_posts_status_created_at_index');
                $table->index(['status', 'moderated_at'], 'moderation_posts_status_moderated_at_index');
            });
        }

        // 2. Table de modération pour les commentaires
        if (!Schema::hasTable('moderation_comments')) {
            Schema::create('moderation_comments', function (Blueprint $table) {
                $table->id();

                $table->foreignId('comment_id')
                    ->constrained('comments')
                    ->cascadeOnDelete()
                    ->unique('moderation_comments_comment_id_unique');

                $table->enum('status', [
                    'pending',
                    'approved',
                    'review',
                    'rejected'
                ])->default('pending');

                $table->float('toxicity_score')->nullable();
                $table->float('spam_score')->nullable();
                $table->float('hate_score')->nullable();
                $table->float('violence_score')->nullable();

                $table->json('result_raw')->nullable();
                $table->text('reason')->nullable();

                $table->string('content_hash', 64)->nullable()->index('moderation_comments_content_hash_index');

                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();

                $table->index('status', 'moderation_comments_status_index');
                $table->index(['comment_id', 'status'], 'moderation_comments_comment_id_status_index');
                $table->index(['status', 'created_at'], 'moderation_comments_status_created_at_index');
                $table->index(['status', 'moderated_at'], 'moderation_comments_status_moderated_at_index');
            });
        }

        // 3. Table de modération pour les messages
        if (!Schema::hasTable('moderation_messages')) {
            Schema::create('moderation_messages', function (Blueprint $table) {
                $table->id();

                $table->foreignId('message_id')
                    ->constrained('messages')
                    ->cascadeOnDelete()
                    ->unique('moderation_messages_message_id_unique');

                $table->enum('status', [
                    'pending',
                    'approved',
                    'review',
                    'rejected'
                ])->default('pending');

                $table->float('toxicity_score')->nullable();
                $table->float('spam_score')->nullable();
                $table->float('hate_score')->nullable();
                $table->float('violence_score')->nullable();

                $table->json('result_raw')->nullable();
                $table->text('reason')->nullable();

                $table->string('content_hash', 64)->nullable()->index('moderation_messages_content_hash_index');

                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();

                $table->index('status', 'moderation_messages_status_index');
                $table->index(['message_id', 'status'], 'moderation_messages_message_id_status_index');
                $table->index(['status', 'created_at'], 'moderation_messages_status_created_at_index');
                $table->index(['status', 'moderated_at'], 'moderation_messages_status_moderated_at_index');
            });
        }

        // 4. Table d'audit (avec noms d'index uniques)
        if (!Schema::hasTable('moderation_audit_log')) {
            Schema::create('moderation_audit_log', function (Blueprint $table) {
                $table->id();

                // Polymorphique
                $table->morphs('moderatable', 'audit_log_moderatable_index');

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

                $table->timestamp('created_at');

                // Index avec noms uniques
                $table->index('action', 'audit_log_action_index');
                $table->index('created_at', 'audit_log_created_at_index');
                $table->index(['actor_type', 'actor_id'], 'audit_log_actor_index');
            });
        }

        // 5. Table pour les signalements
        if (!Schema::hasTable('moderation_reports')) {
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

                $table->index(['content_type', 'content_id'], 'reports_content_index');
                $table->index('status', 'reports_status_index');
                $table->index('reason', 'reports_reason_index');
                $table->index(['reporter_id', 'created_at'], 'reports_reporter_created_index');
            });
        }

        // 6. Vue matérialisée
        try {
            DB::statement("
                CREATE OR REPLACE VIEW moderation_stats AS
                SELECT 
                    'post' as content_type,
                    status,
                    COUNT(*) as total,
                    AVG(toxicity_score) as avg_toxicity,
                    AVG(spam_score) as avg_spam,
                    AVG(hate_score) as avg_hate,
                    AVG(violence_score) as avg_violence
                FROM moderation_posts
                GROUP BY status
                
                UNION ALL
                
                SELECT 
                    'comment' as content_type,
                    status,
                    COUNT(*) as total,
                    AVG(toxicity_score) as avg_toxicity,
                    AVG(spam_score) as avg_spam,
                    AVG(hate_score) as avg_hate,
                    AVG(violence_score) as avg_violence
                FROM moderation_comments
                GROUP BY status
                
                UNION ALL
                
                SELECT 
                    'message' as content_type,
                    status,
                    COUNT(*) as total,
                    AVG(toxicity_score) as avg_toxicity,
                    AVG(spam_score) as avg_spam,
                    AVG(hate_score) as avg_hate,
                    AVG(violence_score) as avg_violence
                FROM moderation_messages
                GROUP BY status
            ");
        } catch (\Exception $e) {
            // La vue existe peut-être déjà
        }
    }

    public function down(): void
    {
        // Supprimer la vue
        try {
            DB::statement('DROP VIEW IF EXISTS moderation_stats');
        } catch (\Exception $e) {
            // Ignorer
        }

        // Supprimer les tables
        Schema::dropIfExists('moderation_reports');
        Schema::dropIfExists('moderation_audit_log');
        Schema::dropIfExists('moderation_messages');
        Schema::dropIfExists('moderation_comments');
        Schema::dropIfExists('moderation_posts');
    }
};
