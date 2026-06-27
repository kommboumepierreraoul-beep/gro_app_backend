<?php

namespace App\Services\Moderation;

use App\Models\Post;
use App\Models\Comment;
use App\Models\Message;
use App\Models\ModerationPost;
use App\Models\ModerationComment;
use App\Models\ModerationMessage;
use App\Services\Moderation\Contracts\AIModerationInterface;
use Illuminate\Support\Facades\Log;

class SyncModerationService
{
    private AIModerationInterface $provider;
    private DecisionEngine $decisionEngine;
    private FastModerationLayer $fastLayer;

    public function __construct(
        AIModerationInterface $provider,
        DecisionEngine $decisionEngine,
        FastModerationLayer $fastLayer
    ) {
        $this->provider = $provider;
        $this->decisionEngine = $decisionEngine;
        $this->fastLayer = $fastLayer;
    }

    /**
     * Modération synchrone pour un post
     */
    public function moderatePostSync(Post $post): array
    {
        try {
            // 1. Fast Moderation Layer
            $fastDecision = $this->fastLayer->check($post->content, $post->user_id);

            if ($fastDecision !== null) {
                $status = $fastDecision;
                $reason = 'Décision automatique (filtre rapide)';
                $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                $source = 'system';
            } else {
                // 2. Analyse IA
                try {
                    $result = $this->provider->analyzeText($post->content);
                    $scores = [
                        'toxicity' => $result['toxicity'] ?? 0,
                        'spam' => $result['spam'] ?? 0,
                        'hate' => $result['hate'] ?? 0,
                        'violence' => $result['violence'] ?? 0,
                    ];

                    // Décision IA
                    $iaDecision = $this->decisionEngine->decide($scores);
                    $reason = $result['reason'] ?? 'Décision IA';
                    $source = $this->provider->getProviderName();

                    // Déterminer le statut final
                    if ($iaDecision === 'reject') {
                        $status = 'rejected';
                        $reason = $reason ?: 'Contenu rejeté par l\'IA';
                    } elseif ($iaDecision === 'review') {
                        // Mode auto_approve : approuver automatiquement
                        if (config('moderation.mode') === 'auto_approve') {
                            $status = 'approved';
                            $reason = '✅ Approuvé automatiquement (seuil modéré)';
                        } else {
                            $status = 'review';
                            $reason = $reason ?: 'En attente de vérification manuelle';
                        }
                    } else {
                        // approve
                        $status = 'approved';
                        $reason = $reason ?: 'Contenu approuvé par l\'IA';
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur IA, fallback approve', [
                        'post_id' => $post->id,
                        'error' => $e->getMessage(),
                    ]);
                    $status = 'approved';
                    $reason = '✅ Approuvé automatiquement (fallback)';
                    $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                    $source = 'fallback';
                }
            }

            // 3. Mise à jour de la modération
            $moderation = $post->moderation ?? new ModerationPost([
                'post_id' => $post->id,
                'content_hash' => $post->generateContentHash(),
            ]);

            $moderation->fill([
                'status' => $status,
                'toxicity_score' => $scores['toxicity'] ?? 0,
                'spam_score' => $scores['spam'] ?? 0,
                'hate_score' => $scores['hate'] ?? 0,
                'violence_score' => $scores['violence'] ?? 0,
                'reason' => $reason,
                'moderated_at' => now(),
                'result_raw' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
            ]);

            $moderation->save();

            // 4. Audit log
            $moderation->auditLogs()->create([
                'action' => $status,
                'actor_type' => $fastDecision !== null ? 'system' : 'ai',
                'actor_id' => null,
                'payload' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
                'created_at' => now(),
            ]);

            Log::info('Modération terminée', [
                'post_id' => $post->id,
                'status' => $status,
                'source' => $source,
                'mode' => config('moderation.mode', 'auto_approve'),
            ]);

            return [
                'status' => $status,
                'reason' => $reason,
                'scores' => $scores,
                'source' => $source,
                'moderation' => $moderation,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur modération post', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback : approuver automatiquement
            return [
                'status' => 'approved',
                'reason' => '✅ Approuvé automatiquement (erreur)',
                'scores' => ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0],
                'source' => 'error',
                'moderation' => $post->moderation ?? null,
            ];
        }
    }

    /**
     * Modération synchrone pour un commentaire
     */
    public function moderateCommentSync(Comment $comment): array
    {
        try {
            // 1. Fast Moderation Layer
            $fastDecision = $this->fastLayer->check($comment->content, $comment->user_id);

            if ($fastDecision !== null) {
                $status = $fastDecision;
                $reason = 'Décision automatique (filtre rapide)';
                $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                $source = 'system';
            } else {
                // 2. Analyse IA
                try {
                    $result = $this->provider->analyzeText($comment->content);
                    $scores = [
                        'toxicity' => $result['toxicity'] ?? 0,
                        'spam' => $result['spam'] ?? 0,
                        'hate' => $result['hate'] ?? 0,
                        'violence' => $result['violence'] ?? 0,
                    ];

                    $iaDecision = $this->decisionEngine->decide($scores);
                    $reason = $result['reason'] ?? 'Décision IA';
                    $source = $this->provider->getProviderName();

                    if ($iaDecision === 'reject') {
                        $status = 'rejected';
                        $reason = $reason ?: 'Contenu rejeté par l\'IA';
                    } elseif ($iaDecision === 'review') {
                        if (config('moderation.mode') === 'auto_approve') {
                            $status = 'approved';
                            $reason = '✅ Approuvé automatiquement (seuil modéré)';
                        } else {
                            $status = 'review';
                            $reason = $reason ?: 'En attente de vérification manuelle';
                        }
                    } else {
                        $status = 'approved';
                        $reason = $reason ?: 'Contenu approuvé par l\'IA';
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur IA commentaire, fallback approve', [
                        'comment_id' => $comment->id,
                        'error' => $e->getMessage(),
                    ]);
                    $status = 'approved';
                    $reason = '✅ Approuvé automatiquement (fallback)';
                    $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                    $source = 'fallback';
                }
            }

            // 3. Mise à jour
            $moderation = $comment->moderation ?? new ModerationComment([
                'comment_id' => $comment->id,
                'content_hash' => $comment->generateContentHash(),
            ]);

            $moderation->fill([
                'status' => $status,
                'toxicity_score' => $scores['toxicity'] ?? 0,
                'spam_score' => $scores['spam'] ?? 0,
                'hate_score' => $scores['hate'] ?? 0,
                'violence_score' => $scores['violence'] ?? 0,
                'reason' => $reason,
                'moderated_at' => now(),
                'result_raw' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
            ]);

            $moderation->save();

            // 4. Audit log
            $moderation->auditLogs()->create([
                'action' => $status,
                'actor_type' => $fastDecision !== null ? 'system' : 'ai',
                'actor_id' => null,
                'payload' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
                'created_at' => now(),
            ]);

            Log::info('Modération commentaire terminée', [
                'comment_id' => $comment->id,
                'status' => $status,
            ]);

            return [
                'status' => $status,
                'reason' => $reason,
                'scores' => $scores,
                'source' => $source,
                'moderation' => $moderation,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur modération commentaire', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'approved',
                'reason' => '✅ Approuvé automatiquement (erreur)',
                'scores' => ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0],
                'source' => 'error',
                'moderation' => $comment->moderation ?? null,
            ];
        }
    }

    /**
     * Modération synchrone pour un message
     */
    public function moderateMessageSync(Message $message): array
    {
        try {
            // 1. Fast Moderation Layer
            $fastDecision = $this->fastLayer->check($message->content, $message->sender_id);

            if ($fastDecision !== null) {
                $status = $fastDecision;
                $reason = 'Décision automatique (filtre rapide)';
                $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                $source = 'system';
            } else {
                // 2. Analyse IA
                try {
                    $result = $this->provider->analyzeText($message->content);
                    $scores = [
                        'toxicity' => $result['toxicity'] ?? 0,
                        'spam' => $result['spam'] ?? 0,
                        'hate' => $result['hate'] ?? 0,
                        'violence' => $result['violence'] ?? 0,
                    ];

                    $iaDecision = $this->decisionEngine->decide($scores);
                    $reason = $result['reason'] ?? 'Décision IA';
                    $source = $this->provider->getProviderName();

                    if ($iaDecision === 'reject') {
                        $status = 'rejected';
                        $reason = $reason ?: 'Contenu rejeté par l\'IA';
                    } elseif ($iaDecision === 'review') {
                        if (config('moderation.mode') === 'auto_approve') {
                            $status = 'approved';
                            $reason = '✅ Approuvé automatiquement (seuil modéré)';
                        } else {
                            $status = 'review';
                            $reason = $reason ?: 'En attente de vérification manuelle';
                        }
                    } else {
                        $status = 'approved';
                        $reason = $reason ?: 'Contenu approuvé par l\'IA';
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur IA message, fallback approve', [
                        'message_id' => $message->id,
                        'error' => $e->getMessage(),
                    ]);
                    $status = 'approved';
                    $reason = '✅ Approuvé automatiquement (fallback)';
                    $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                    $source = 'fallback';
                }
            }

            // 3. Mise à jour
            $moderation = $message->moderation ?? new ModerationMessage([
                'message_id' => $message->id,
                'content_hash' => $message->generateContentHash(),
            ]);

            $moderation->fill([
                'status' => $status,
                'toxicity_score' => $scores['toxicity'] ?? 0,
                'spam_score' => $scores['spam'] ?? 0,
                'hate_score' => $scores['hate'] ?? 0,
                'violence_score' => $scores['violence'] ?? 0,
                'reason' => $reason,
                'moderated_at' => now(),
                'result_raw' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
            ]);

            $moderation->save();

            // 4. Audit log
            $moderation->auditLogs()->create([
                'action' => $status,
                'actor_type' => $fastDecision !== null ? 'system' : 'ai',
                'actor_id' => null,
                'payload' => [
                    'status' => $status,
                    'reason' => $reason,
                    'scores' => $scores,
                    'source' => $source,
                    'mode' => config('moderation.mode', 'auto_approve'),
                ],
                'created_at' => now(),
            ]);

            Log::info('Modération message terminée', [
                'message_id' => $message->id,
                'status' => $status,
            ]);

            return [
                'status' => $status,
                'reason' => $reason,
                'scores' => $scores,
                'source' => $source,
                'moderation' => $moderation,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur modération message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'approved',
                'reason' => '✅ Approuvé automatiquement (erreur)',
                'scores' => ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0],
                'source' => 'error',
                'moderation' => $message->moderation ?? null,
            ];
        }
    }

    /**
     * Modération synchrone générique
     */
    public function moderateSync(string $content, int $userId, string $type = 'post'): array
    {
        try {
            // 1. Fast Moderation Layer
            $fastDecision = $this->fastLayer->check($content, $userId);

            if ($fastDecision !== null) {
                $status = $fastDecision;
                $reason = 'Décision automatique (filtre rapide)';
                $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                $source = 'system';
            } else {
                // 2. Analyse IA
                try {
                    $result = $this->provider->analyzeText($content);
                    $scores = [
                        'toxicity' => $result['toxicity'] ?? 0,
                        'spam' => $result['spam'] ?? 0,
                        'hate' => $result['hate'] ?? 0,
                        'violence' => $result['violence'] ?? 0,
                    ];

                    $iaDecision = $this->decisionEngine->decide($scores);
                    $reason = $result['reason'] ?? 'Décision IA';
                    $source = $this->provider->getProviderName();

                    if ($iaDecision === 'reject') {
                        $status = 'rejected';
                        $reason = $reason ?: 'Contenu rejeté par l\'IA';
                    } elseif ($iaDecision === 'review') {
                        if (config('moderation.mode') === 'auto_approve') {
                            $status = 'approved';
                            $reason = '✅ Approuvé automatiquement (seuil modéré)';
                        } else {
                            $status = 'review';
                            $reason = $reason ?: 'En attente de vérification manuelle';
                        }
                    } else {
                        $status = 'approved';
                        $reason = $reason ?: 'Contenu approuvé par l\'IA';
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur IA générique, fallback approve', [
                        'error' => $e->getMessage(),
                    ]);
                    $status = 'approved';
                    $reason = '✅ Approuvé automatiquement (fallback)';
                    $scores = ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0];
                    $source = 'fallback';
                }
            }

            return [
                'status' => $status,
                'reason' => $reason,
                'scores' => $scores,
                'source' => $source,
            ];
        } catch (\Exception $e) {
            Log::error('Erreur modération générique', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'approved',
                'reason' => '✅ Approuvé automatiquement (erreur)',
                'scores' => ['toxicity' => 0, 'spam' => 0, 'hate' => 0, 'violence' => 0],
                'source' => 'error',
            ];
        }
    }

    /**
     * Re-modérer un post (forcer la réanalyse)
     */
    public function reanalyzePost(Post $post): array
    {
        // Réinitialiser la modération
        if ($post->moderation) {
            $post->moderation->update([
                'status' => 'pending',
                'moderated_at' => null,
                'toxicity_score' => null,
                'spam_score' => null,
                'hate_score' => null,
                'violence_score' => null,
                'result_raw' => null,
                'reason' => null,
                'content_hash' => $post->generateContentHash(),
            ]);
        }

        // Lancer une nouvelle analyse
        return $this->moderatePostSync($post);
    }

    /**
     * Re-modérer un commentaire
     */
    public function reanalyzeComment(Comment $comment): array
    {
        if ($comment->moderation) {
            $comment->moderation->update([
                'status' => 'pending',
                'moderated_at' => null,
                'toxicity_score' => null,
                'spam_score' => null,
                'hate_score' => null,
                'violence_score' => null,
                'result_raw' => null,
                'reason' => null,
                'content_hash' => $comment->generateContentHash(),
            ]);
        }

        return $this->moderateCommentSync($comment);
    }

    /**
     * Re-modérer un message
     */
    public function reanalyzeMessage(Message $message): array
    {
        if ($message->moderation) {
            $message->moderation->update([
                'status' => 'pending',
                'moderated_at' => null,
                'toxicity_score' => null,
                'spam_score' => null,
                'hate_score' => null,
                'violence_score' => null,
                'result_raw' => null,
                'reason' => null,
                'content_hash' => $message->generateContentHash(),
            ]);
        }

        return $this->moderateMessageSync($message);
    }

    /**
     * Obtenir le statut de modération pour un contenu
     */
    public function getModerationStatus($content): array
    {
        if ($content instanceof Post) {
            $moderation = $content->moderation;
        } elseif ($content instanceof Comment) {
            $moderation = $content->moderation;
        } elseif ($content instanceof Message) {
            $moderation = $content->moderation;
        } else {
            return ['status' => 'pending', 'reason' => null];
        }

        if (!$moderation) {
            return ['status' => 'pending', 'reason' => null];
        }

        return [
            'status' => $moderation->status,
            'reason' => $moderation->reason,
            'scores' => [
                'toxicity' => $moderation->toxicity_score,
                'spam' => $moderation->spam_score,
                'hate' => $moderation->hate_score,
                'violence' => $moderation->violence_score,
            ],
            'moderated_at' => $moderation->moderated_at,
        ];
    }

    /**
     * Vérifier si un contenu est visible
     */
    public function isContentVisible($content): bool
    {
        $status = $this->getModerationStatus($content)['status'] ?? 'pending';
        return $status === 'approved';
    }

    /**
     * Obtenir le provider actuel
     */
    public function getCurrentProvider(): AIModerationInterface
    {
        return $this->provider;
    }

    /**
     * Changer de provider
     */
    public function setProvider(AIModerationInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }
}
