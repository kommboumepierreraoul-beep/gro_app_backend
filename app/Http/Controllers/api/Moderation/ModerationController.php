<?php

namespace App\Http\Controllers\API\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Message;
use App\Models\ModerationPost;
use App\Models\ModerationComment;
use App\Models\ModerationMessage;
use App\Models\ModerationAuditLog;
use App\Models\ModerationReport;
use App\Services\Moderation\ModerationService;
use App\Services\Moderation\DecisionEngine;
use App\Jobs\Moderation\ModeratePostJob;
use App\Jobs\Moderation\ModerateCommentJob;
use App\Jobs\Moderation\ModerateMessageJob;
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
    // STATISTIQUES
    // ────────────────────────────────────────────────────────────────────────────

    public function stats(Request $request)
    {
        $stats = [
            'posts' => ModerationPost::getStats(),
            'comments' => ModerationComment::getStats(),
            'messages' => ModerationMessage::getStats(),
            'audit' => ModerationAuditLog::getStats(),
            'reports' => ModerationReport::getStats(),
            'overall' => [
                'total_pending' => ModerationPost::pending()->count() +
                    ModerationComment::pending()->count() +
                    ModerationMessage::pending()->count(),
                'total_review' => ModerationPost::review()->count() +
                    ModerationComment::review()->count() +
                    ModerationMessage::review()->count(),
                'total_rejected' => ModerationPost::rejected()->count() +
                    ModerationComment::rejected()->count() +
                    ModerationMessage::rejected()->count(),
                'total_approved' => ModerationPost::approved()->count() +
                    ModerationComment::approved()->count() +
                    ModerationMessage::approved()->count(),
            ]
        ];

        return response()->json($stats);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // FILES DE REVIEW
    // ────────────────────────────────────────────────────────────────────────────

    public function reviewQueuePosts(Request $request)
    {
        $posts = Post::reviewQueue()
            ->with(['author', 'moderation'])
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    public function reviewQueueComments(Request $request)
    {
        $comments = Comment::reviewQueue()
            ->with(['author', 'post', 'moderation'])
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function reviewQueueMessages(Request $request)
    {
        $messages = Message::reviewQueue()
            ->with(['sender', 'conversation', 'moderation'])
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // ACTIONS DE MODÉRATION - ADMIN
    // ────────────────────────────────────────────────────────────────────────────

    public function moderatePost(Request $request, Post $post)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,review',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moderation = $post->moderation;

        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce post'
            ], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent modérer.'
            ], 403);
        }

        $newStatus = $request->action;
        $moderation->updateStatus(
            $newStatus,
            'moderator',
            $user->id,
            ['reason' => $request->reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Post modéré avec succès',
            'data' => [
                'post' => $post->fresh(),
                'moderation' => $moderation->fresh(),
            ]
        ]);
    }

    public function moderateComment(Request $request, Comment $comment)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,review',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moderation = $comment->moderation;

        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce commentaire'
            ], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent modérer.'
            ], 403);
        }

        $newStatus = $request->action;
        $moderation->updateStatus(
            $newStatus,
            'moderator',
            $user->id,
            ['reason' => $request->reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Commentaire modéré avec succès',
            'data' => [
                'comment' => $comment->fresh(),
                'moderation' => $moderation->fresh(),
            ]
        ]);
    }

    public function moderateMessage(Request $request, Message $message)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject,review',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $moderation = $message->moderation;

        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce message'
            ], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé. Seuls les administrateurs peuvent modérer.'
            ], 403);
        }

        $newStatus = $request->action;
        $moderation->updateStatus(
            $newStatus,
            'moderator',
            $user->id,
            ['reason' => $request->reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Message modéré avec succès',
            'data' => [
                'message' => $message->fresh(),
                'moderation' => $moderation->fresh(),
            ]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // RÉANALYSE
    // ────────────────────────────────────────────────────────────────────────────

    public function reanalyzePost(Request $request, Post $post)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        ModeratePostJob::dispatch($post)->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Post envoyé pour réanalyse',
            'data' => ['post_id' => $post->id]
        ]);
    }

    public function reanalyzeComment(Request $request, Comment $comment)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        ModerateCommentJob::dispatch($comment)->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Commentaire envoyé pour réanalyse',
            'data' => ['comment_id' => $comment->id]
        ]);
    }

    public function reanalyzeMessage(Request $request, Message $message)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        ModerateMessageJob::dispatch($message)->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé pour réanalyse',
            'data' => ['message_id' => $message->id]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // DÉTAILS DE MODÉRATION
    // ────────────────────────────────────────────────────────────────────────────

    public function getPostModeration(Request $request, Post $post)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
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
                'moderation' => $moderation,
                'audit_logs' => $moderation->auditLogs()->latest()->get(),
            ]
        ]);
    }

    public function getCommentModeration(Request $request, Comment $comment)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $moderation = $comment->moderation;

        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce commentaire'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'moderation' => $moderation,
                'audit_logs' => $moderation->auditLogs()->latest()->get(),
            ]
        ]);
    }

    public function getMessageModeration(Request $request, Message $message)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if ($message->sender_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $moderation = $message->moderation;

        if (!$moderation) {
            return response()->json([
                'success' => false,
                'error' => 'Aucune modération trouvée pour ce message'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message_id' => $message->id,
                'moderation' => $moderation,
                'audit_logs' => $moderation->auditLogs()->latest()->get(),
            ]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // AUDIT
    // ────────────────────────────────────────────────────────────────────────────

    public function auditLog(Request $request)
    {
        $query = ModerationAuditLog::with('actor')
            ->orderBy('created_at', 'desc');

        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('actor_type')) {
            $query->where('actor_type', $request->actor_type);
        }

        if ($request->has('content_type')) {
            $query->where('moderatable_type', $request->content_type);
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
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $logs = ModerationAuditLog::with('actor')
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

    // ────────────────────────────────────────────────────────────────────────────
    // ACTIONS EN MASSE
    // ────────────────────────────────────────────────────────────────────────────

    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'type' => 'required|in:posts,comments,messages',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $ids = $request->ids;
        $type = $request->type;

        DB::beginTransaction();

        try {
            switch ($type) {
                case 'posts':
                    $moderations = ModerationPost::whereIn('post_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('approved', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => 'Approbation en masse',
                        ]);
                    }
                    break;

                case 'comments':
                    $moderations = ModerationComment::whereIn('comment_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('approved', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => 'Approbation en masse',
                        ]);
                    }
                    break;

                case 'messages':
                    $moderations = ModerationMessage::whereIn('message_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('approved', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => 'Approbation en masse',
                        ]);
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenus approuvés en masse',
                'count' => count($ids),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk approve error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de l\'approbation en masse',
            ], 500);
        }
    }

    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'type' => 'required|in:posts,comments,messages',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $ids = $request->ids;
        $type = $request->type;
        $reason = $request->reason ?? 'Rejet en masse';

        DB::beginTransaction();

        try {
            switch ($type) {
                case 'posts':
                    $moderations = ModerationPost::whereIn('post_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('rejected', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => $reason,
                        ]);
                    }
                    break;

                case 'comments':
                    $moderations = ModerationComment::whereIn('comment_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('rejected', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => $reason,
                        ]);
                    }
                    break;

                case 'messages':
                    $moderations = ModerationMessage::whereIn('message_id', $ids)->get();
                    foreach ($moderations as $moderation) {
                        $moderation->updateStatus('rejected', 'moderator', $user->id, [
                            'bulk_action' => true,
                            'reason' => $reason,
                        ]);
                    }
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenus rejetés en masse',
                'count' => count($ids),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk reject error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors du rejet en masse',
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // MES CONTENUS
    // ────────────────────────────────────────────────────────────────────────────

    public function myPendingContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        $posts = Post::with(['author', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $comments = Comment::with(['author', 'post', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $messages = Message::with(['sender', 'conversation', 'moderation'])
            ->where('sender_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'comments' => $comments,
                'messages' => $messages,
            ]
        ]);
    }

    public function myRejectedContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        $posts = Post::with(['author', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $comments = Comment::with(['author', 'post', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $messages = Message::with(['sender', 'conversation', 'moderation'])
            ->where('sender_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'comments' => $comments,
                'messages' => $messages,
            ]
        ]);
    }

    public function myApprovedContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        $posts = Post::with(['author', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $comments = Comment::with(['author', 'post', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $messages = Message::with(['sender', 'conversation', 'moderation'])
            ->where('sender_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'comments' => $comments,
                'messages' => $messages,
            ]
        ]);
    }

    public function myReviewContent(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        $posts = Post::with(['author', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'review');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $comments = Comment::with(['author', 'post', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'review');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        $messages = Message::with(['sender', 'conversation', 'moderation'])
            ->where('sender_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'review');
            })
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'comments' => $comments,
                'messages' => $messages,
            ]
        ]);
    }

    /**
     * Récupérer le résumé de mes modérations
     */
    public function myModerationSummary(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        // ✅ Avec des guillemets simples '...'
        $postStats = ModerationPost::whereHas('post', function ($q) use ($user) {
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

        $commentStats = ModerationComment::whereHas('comment', function ($q) use ($user) {
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

        $messageStats = ModerationMessage::whereHas('message', function ($q) use ($user) {
            $q->where('sender_id', $user->id);
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
                'posts' => [
                    'total' => $postStats->total ?? 0,
                    'pending' => $postStats->pending ?? 0,
                    'approved' => $postStats->approved ?? 0,
                    'review' => $postStats->review ?? 0,
                    'rejected' => $postStats->rejected ?? 0,
                ],
                'comments' => [
                    'total' => $commentStats->total ?? 0,
                    'pending' => $commentStats->pending ?? 0,
                    'approved' => $commentStats->approved ?? 0,
                    'review' => $commentStats->review ?? 0,
                    'rejected' => $commentStats->rejected ?? 0,
                ],
                'messages' => [
                    'total' => $messageStats->total ?? 0,
                    'pending' => $messageStats->pending ?? 0,
                    'approved' => $messageStats->approved ?? 0,
                    'review' => $messageStats->review ?? 0,
                    'rejected' => $messageStats->rejected ?? 0,
                ],
                'total_pending' => ($postStats->pending ?? 0) + ($commentStats->pending ?? 0) + ($messageStats->pending ?? 0),
                'total_review' => ($postStats->review ?? 0) + ($commentStats->review ?? 0) + ($messageStats->review ?? 0),
                'total_rejected' => ($postStats->rejected ?? 0) + ($commentStats->rejected ?? 0) + ($messageStats->rejected ?? 0),
                'total_approved' => ($postStats->approved ?? 0) + ($commentStats->approved ?? 0) + ($messageStats->approved ?? 0),
            ]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // SIGNALEMENTS
    // ────────────────────────────────────────────────────────────────────────────

    public function createReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'content_type' => 'required|in:post,comment,message,user',
            'content_id' => 'required|integer',
            'reason' => 'required|string|in:spam,harassment,hate_speech,violence,inappropriate,misinformation,other',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        // Vérifier que le signalement n'existe pas déjà
        $existing = ModerationReport::where([
            'reporter_id' => $user->id,
            'content_type' => $request->content_type,
            'content_id' => $request->content_id,
        ])->whereIn('status', ['pending', 'reviewing'])->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'error' => 'Vous avez déjà signalé ce contenu',
                'report_id' => $existing->id,
            ], 409);
        }

        $report = ModerationReport::create([
            'reporter_id' => $user->id,
            'content_type' => $request->content_type,
            'content_id' => $request->content_id,
            'reason' => $request->reason,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Signalement envoyé avec succès',
            'data' => $report,
        ], 201);
    }

    public function myReports(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Utilisateur non authentifié'], 401);
        }

        $reports = ModerationReport::where('reporter_id', $user->id)
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function allReports(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $reports = ModerationReport::with(['reporter', 'resolver'])
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function pendingReports(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $reports = ModerationReport::where('status', 'pending')
            ->with(['reporter'])
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    public function resolveReport(Request $request, ModerationReport $report)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $report->resolve($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Signalement résolu',
            'data' => $report->fresh(),
        ]);
    }

    public function dismissReport(Request $request, ModerationReport $report)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $report->dismiss($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Signalement rejeté',
            'data' => $report->fresh(),
        ]);
    }

    public function reportStats(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => ModerationReport::getStats(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // PROVIDERS
    // ────────────────────────────────────────────────────────────────────────────

    public function providers(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $providers = $this->moderationService->getAvailableProviders();
        $current = $this->moderationService->getCurrentProvider();

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $providers,
                'current' => [
                    'name' => $current->getProviderName(),
                    'model' => $current->getModel(),
                ],
            ]
        ]);
    }

    public function switchProvider(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:claude,openai,gemini',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $provider = $request->provider;

        if (!$this->moderationService->isProviderAvailable($provider)) {
            return response()->json([
                'success' => false,
                'error' => "Le provider {$provider} n'est pas disponible. Vérifiez votre clé API."
            ], 400);
        }

        $this->moderationService->setProvider($provider);

        return response()->json([
            'success' => true,
            'message' => 'Provider changé avec succès',
            'data' => ['provider' => $provider]
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // TEST
    // ────────────────────────────────────────────────────────────────────────────

    public function test(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Utilisateur non authentifié'
            ], 401);
        }

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Accès non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $imageBase64 = null;
        $mediaType = 'image/jpeg';

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageBase64 = base64_encode(file_get_contents($image->getRealPath()));
            $mediaType = $image->getMimeType();
        }

        $result = $this->moderationService->analyze(
            $request->content,
            $imageBase64,
            $mediaType
        );

        $result['estimated_cost'] = $this->moderationService->getEstimatedCost(
            $request->content,
            $imageBase64
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // CONFIGURATION
    // ────────────────────────────────────────────────────────────────────────────

    public function getThresholds(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->decisionEngine->getThresholds(),
        ]);
    }

    public function updateThresholds(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'Accès non autorisé'], 403);
        }

        $validator = Validator::make($request->all(), [
            'toxicity.review' => 'required|numeric|between:0,1',
            'toxicity.reject' => 'required|numeric|between:0,1',
            'spam.review' => 'required|numeric|between:0,1',
            'spam.reject' => 'required|numeric|between:0,1',
            'hate.review' => 'required|numeric|between:0,1',
            'hate.reject' => 'required|numeric|between:0,1',
            'violence.review' => 'required|numeric|between:0,1',
            'violence.reject' => 'required|numeric|between:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $this->decisionEngine->setThresholds($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Seuils mis à jour avec succès',
            'data' => $this->decisionEngine->getThresholds(),
        ]);
    }
}
