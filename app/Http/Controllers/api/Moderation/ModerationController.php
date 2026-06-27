<?php

namespace App\Http\Controllers\API\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\ModerationPost;
use App\Models\ModerationAuditLog;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\DecisionEngine;
use App\Jobs\Moderation\ModeratePostJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ModerationController extends Controller
{
    protected ModerationService $moderationService;
    protected DecisionEngine $decisionEngine;

    public function __construct(
        ModerationService $moderationService,
        DecisionEngine $decisionEngine
    ) {
        $this->moderationService = $moderationService;
        $this->decisionEngine = $decisionEngine;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // STATISTIQUES - POSTS UNIQUEMENT
    // ────────────────────────────────────────────────────────────────────────────

    public function stats(Request $request)
    {
        try {
            $stats = [
                'posts' => [
                    'pending' => ModerationPost::where('status', 'pending')->count(),
                    'approved' => ModerationPost::where('status', 'approved')->count(),
                    'rejected' => ModerationPost::where('status', 'rejected')->count(),
                    'review' => ModerationPost::where('status', 'review')->count(),
                    'total' => ModerationPost::count(),
                ],
                'overall' => [
                    'total_pending' => ModerationPost::where('status', 'pending')->count(),
                    'total_review' => ModerationPost::where('status', 'review')->count(),
                    'total_rejected' => ModerationPost::where('status', 'rejected')->count(),
                    'total_approved' => ModerationPost::where('status', 'approved')->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur stats modération posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement des statistiques',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // STATUT DE PUBLICATION - UTILISATEUR
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Récupérer le statut de publication de l'utilisateur
     * GET /api/moderation/publishing-status
     */
    public function getPublishingStatus(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        try {
            // Récupérer les statistiques de l'utilisateur
            $stats = $this->getUserModerationStats($user);
            $canPublish = $user->canPublish();
            $blockedUntil = $user->getPublishingBlockedUntil();
            $remainingTime = $user->getRemainingBlockTime();
            $reasons = $this->getBlockReasons($user, $stats);
            $estimatedWaitTime = $this->getEstimatedWaitTime($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'can_publish' => $canPublish,
                    'blocked_until' => $blockedUntil,
                    'remaining_time' => $remainingTime,
                    'estimated_wait_time' => $estimatedWaitTime,
                    'stats' => $stats,
                    'reasons' => $reasons,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getPublishingStatus', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut'
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques de modération de l'utilisateur
     */
    private function getUserModerationStats($user): array
    {
        // Récupérer les 20 derniers posts modérés
        $recentPosts = $user->posts()
            ->whereHas('moderation')
            ->with('moderation')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $totalRecent = $recentPosts->count();
        $rejected = $recentPosts->filter(function ($post) {
            return $post->moderation && $post->moderation->status === 'rejected';
        })->count();

        $rejectionRate = $totalRecent > 0 ? round(($rejected / $totalRecent) * 100, 2) : 0;

        // Compter les posts en attente
        $pendingCount = $user->posts()
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->count();

        // Compter les rejets consécutifs
        $consecutiveRejected = 0;
        $latestPosts = $user->posts()
            ->whereHas('moderation')
            ->with('moderation')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($latestPosts as $post) {
            if ($post->moderation && $post->moderation->status === 'rejected') {
                $consecutiveRejected++;
            } else {
                break;
            }
        }

        // Compter les posts en attente depuis plus de 24h
        $pendingSince24h = $user->posts()
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return [
            'total_recent' => $totalRecent,
            'rejected_count' => $rejected,
            'rejection_rate' => $rejectionRate,
            'pending_count' => $pendingCount,
            'consecutive_rejected' => $consecutiveRejected,
            'pending_since_24h' => $pendingSince24h,
            'can_publish' => $user->canPublish(),
        ];
    }

    /**
     * Obtenir les raisons du blocage
     */
    private function getBlockReasons($user, array $stats): array
    {
        $reasons = [];
        $rejectedCount = $stats['rejected_count'];
        $pendingCount = $stats['pending_count'];
        $rejectionRate = $stats['rejection_rate'];
        $consecutiveRejected = $stats['consecutive_rejected'];
        $pendingSince24h = $stats['pending_since_24h'];

        // ⛔ Plus de 10 posts rejetés
        if ($rejectedCount >= 10) {
            $reasons[] = [
                'reason' => "Vous avez {$rejectedCount} posts rejetés (limite: 10)",
                'type' => 'permanent',
                'action' => 'Contactez un administrateur pour résoudre ce problème.',
            ];
        }

        // ⛔ Plus de 5 rejets ET plus de 5 en attente
        if ($rejectedCount >= 5 && $pendingCount >= 5) {
            $reasons[] = [
                'reason' => "Vous avez {$rejectedCount} rejets et {$pendingCount} posts en attente",
                'type' => 'temporary',
                'action' => "Attendez que vos posts en attente soient modérés.",
            ];
        }

        // ⛔ Plus de 10 posts en attente
        if ($pendingCount >= 10) {
            $reasons[] = [
                'reason' => "Vous avez {$pendingCount} posts en attente (limite: 10)",
                'type' => 'temporary',
                'action' => "Attendez que vos posts soient modérés avant d'en publier de nouveaux.",
            ];
        }

        // ⛔ Taux de rejet > 30%
        if ($rejectionRate > 30 && $stats['total_recent'] >= 5) {
            $reasons[] = [
                'reason' => "Taux de rejet de {$rejectionRate}% sur les {$stats['total_recent']} derniers posts (limite: 30%)",
                'type' => 'temporary',
                'action' => "Publiez du contenu de meilleure qualité pour faire baisser votre taux de rejet.",
            ];
        }

        // ⛔ Plus de 5 posts en attente depuis plus de 24h
        if ($pendingSince24h > 5) {
            $reasons[] = [
                'reason' => "{$pendingSince24h} posts en attente depuis plus de 24h",
                'type' => 'temporary',
                'action' => "Une modération manuelle est en cours. Merci de patienter.",
            ];
        }

        // ⛔ Rejets consécutifs (>= 3)
        if ($consecutiveRejected >= 3) {
            $reasons[] = [
                'reason' => "{$consecutiveRejected} rejets consécutifs",
                'type' => 'temporary',
                'action' => "Prenez le temps de revoir la qualité de vos publications.",
            ];
        }

        // Si aucune raison, l'utilisateur peut publier
        if (empty($reasons)) {
            $reasons[] = [
                'reason' => 'Vous êtes autorisé à publier',
                'type' => 'allowed',
                'action' => 'Vous pouvez créer de nouvelles publications.',
            ];
        }

        return $reasons;
    }

    /**
     * Estimer le temps d'attente avant de pouvoir reposter
     */
    private function getEstimatedWaitTime($user): ?string
    {
        // Si l'utilisateur peut publier, pas d'attente
        if ($user->canPublish()) {
            return null;
        }

        // Si l'utilisateur a un blocage permanent
        $rejectedCount = $user->getRejectedPostsCount();
        if ($rejectedCount >= 10) {
            return 'Indéterminé - Contactez un administrateur';
        }

        // Calcul basé sur le nombre de posts en attente
        $pendingCount = $user->getPendingPostsCount();

        if ($pendingCount === 0) {
            return 'Très prochainement';
        }

        // Estimation: 1h par post en attente (à adapter selon votre modération)
        $hours = $pendingCount;

        if ($hours <= 1) {
            return 'Moins d\'une heure';
        }

        if ($hours < 24) {
            return "Environ {$hours} heures";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        if ($days === 1) {
            return "1 jour et {$remainingHours} heures";
        }

        return "{$days} jours et {$remainingHours} heures";
    }

    // ────────────────────────────────────────────────────────────────────────────
    // FILES DE REVIEW - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function reviewQueuePosts(Request $request)
    {
        try {
            $posts = Post::whereHas('moderation', function ($query) {
                $query->where('status', 'pending')
                    ->orWhere('status', 'review');
            })
                ->with(['author', 'moderation'])
                ->orderBy('created_at', 'asc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur review queue posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de la file d\'attente',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // ACTIONS DE MODÉRATION - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function moderatePost(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,review',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent modérer.'
            ], 403);
        }

        $moderation = $post->moderation;
        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce post'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $newStatus = $request->action;
            $reason = $request->reason;

            // Mettre à jour la modération
            $moderation->update([
                'status' => $newStatus,
                'moderated_by' => $user->id,
                'moderated_at' => now(),
                'reason' => $reason,
            ]);

            // Audit log
            ModerationAuditLog::create([
                'moderatable_type' => Post::class,
                'moderatable_id' => $post->id,
                'action' => $newStatus,
                'actor_type' => 'moderator',
                'actor_id' => $user->id,
                'payload' => [
                    'reason' => $reason,
                    'post_id' => $post->id,
                    'post_title' => $post->title,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Post modéré avec succès',
                'data' => [
                    'post' => $post->fresh(),
                    'moderation' => $moderation->fresh(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur moderation post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la modération',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // RÉANALYSE - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function reanalyzePost(Request $request, Post $post)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        try {
            ModeratePostJob::dispatch($post)->afterCommit();

            return response()->json([
                'success' => true,
                'message' => 'Post envoyé pour réanalyse',
                'data' => ['post_id' => $post->id]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur reanalyse post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la réanalyse',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // DÉTAILS DE MODÉRATION - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function getPostModeration(Request $request, Post $post)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $moderation = $post->moderation;
        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce post'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'post_title' => $post->title,
                'moderation' => $moderation,
                'audit_logs' => ModerationAuditLog::where('moderatable_type', Post::class)
                    ->where('moderatable_id', $post->id)
                    ->latest()
                    ->get(),
            ]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // MES POSTS - MODÉRATION
    // ────────────────────────────────────────────────────────────────────────────

    public function myPendingContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        try {
            $posts = Post::where('user_id', $user->id)
                ->whereHas('moderation', function ($query) {
                    $query->where('status', 'pending');
                })
                ->with(['author', 'moderation'])
                ->latest()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur my pending posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de vos posts en attente',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myRejectedContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        try {
            $posts = Post::where('user_id', $user->id)
                ->whereHas('moderation', function ($query) {
                    $query->where('status', 'rejected');
                })
                ->with(['author', 'moderation'])
                ->latest()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur my rejected posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de vos posts rejetés',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myApprovedContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        try {
            $posts = Post::where('user_id', $user->id)
                ->whereHas('moderation', function ($query) {
                    $query->where('status', 'approved');
                })
                ->with(['author', 'moderation'])
                ->latest()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur my approved posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de vos posts approuvés',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function myReviewContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        try {
            $posts = Post::where('user_id', $user->id)
                ->whereHas('moderation', function ($query) {
                    $query->where('status', 'review');
                })
                ->with(['author', 'moderation'])
                ->latest()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $posts
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur my review posts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement de vos posts en révision',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // RÉSUMÉ DE MODÉRATION - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function myModerationSummary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Non authentifié'
            ], 401);
        }

        try {
            $stats = ModerationPost::whereHas('post', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
                ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => (int) ($stats->total ?? 0),
                    'pending' => (int) ($stats->pending ?? 0),
                    'approved' => (int) ($stats->approved ?? 0),
                    'review' => (int) ($stats->review ?? 0),
                    'rejected' => (int) ($stats->rejected ?? 0),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur moderation summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du chargement du résumé',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // ACTIONS EN MASSE - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $ids = $request->ids;

        DB::beginTransaction();

        try {
            $moderations = ModerationPost::whereIn('post_id', $ids)->get();
            $count = 0;

            foreach ($moderations as $moderation) {
                $moderation->update([
                    'status' => 'approved',
                    'moderated_by' => $user->id,
                    'moderated_at' => now(),
                    'reason' => 'Approbation en masse',
                ]);

                // Audit log
                ModerationAuditLog::create([
                    'moderatable_type' => Post::class,
                    'moderatable_id' => $moderation->post_id,
                    'action' => 'approved',
                    'actor_type' => 'moderator',
                    'actor_id' => $user->id,
                    'payload' => [
                        'bulk_action' => true,
                        'reason' => 'Approbation en masse',
                    ],
                ]);

                $count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$count} post(s) approuvé(s) en masse",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk approve error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'approbation en masse',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:posts,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $ids = $request->ids;
        $reason = $request->reason ?? 'Rejet en masse';

        DB::beginTransaction();

        try {
            $moderations = ModerationPost::whereIn('post_id', $ids)->get();
            $count = 0;

            foreach ($moderations as $moderation) {
                $moderation->update([
                    'status' => 'rejected',
                    'moderated_by' => $user->id,
                    'moderated_at' => now(),
                    'reason' => $reason,
                ]);

                // Audit log
                ModerationAuditLog::create([
                    'moderatable_type' => Post::class,
                    'moderatable_id' => $moderation->post_id,
                    'action' => 'rejected',
                    'actor_type' => 'moderator',
                    'actor_id' => $user->id,
                    'payload' => [
                        'bulk_action' => true,
                        'reason' => $reason,
                    ],
                ]);

                $count++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$count} post(s) rejeté(s) en masse",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk reject error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du rejet en masse',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // AUDIT - POSTS
    // ────────────────────────────────────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $query = ModerationAuditLog::with('actor')
            ->where('moderatable_type', Post::class)
            ->orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    public function exportAudit(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $logs = ModerationAuditLog::with('actor')
            ->where('moderatable_type', Post::class)
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 1000))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'count' => $logs->count(),
                'exported_at' => now(),
            ]
        ]);
    }
}
