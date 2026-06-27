<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\CommunityNotification;
use App\Models\ModerationComment;
use App\Jobs\Moderation\ModerateCommentJob;
use App\Services\Moderation\FastModerationLayer;
use App\Services\Moderation\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    protected FastModerationLayer $fastLayer;
    protected ModerationService $moderationService;

    public function __construct(
        FastModerationLayer $fastLayer,
        ModerationService $moderationService
    ) {
        $this->fastLayer = $fastLayer;
        $this->moderationService = $moderationService;
    }

    // ── Commentaires d'un post ────────────────────────────────────────────────
    public function index(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        $user = $request->user();

        // Vérifier si le post est visible
        if (!$post->isVisible() && $post->user_id !== $user?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette publication n\'est pas disponible.',
            ], 404);
        }

        $comments = Comment::with(['author.profile', 'replies.author.profile', 'moderation'])
            ->where('post_id', $postId)
            ->whereNull('parent_id')
            ->visible()
            ->latest()
            ->paginate(20);

        $userId = $user?->id;
        $comments->getCollection()->transform(fn($c) => $this->formatComment($c, $userId));

        return response()->json(['success' => true, 'data' => $comments]);
    }

    // ── Ajouter un commentaire avec modération ────────────────────────────────
    public function store(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        // Vérifier si le post est visible
        if (!$post->isVisible() && $post->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas commenter cette publication.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content'   => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Vérifier le parent si présent
        if ($request->parent_id) {
            $parent = Comment::find($request->parent_id);
            if (!$parent || $parent->post_id !== $postId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le commentaire parent est invalide.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Création du commentaire
            $comment = Comment::create([
                'post_id'   => $postId,
                'user_id'   => $user->id,
                'parent_id' => $request->parent_id,
                'content'   => $request->content,
            ]);

            // Incrémenter le compteur du post
            $post->increment('comments_count');

            // ── MODÉRATION ──────────────────────────────────────────────────
            // Fast Moderation Layer
            $fastDecision = $this->fastLayer->check(
                $comment->content,
                $user->id
            );

            // Création de l'enregistrement de modération
            $moderation = ModerationComment::create([
                'comment_id' => $comment->id,
                'status' => $fastDecision ?? 'pending',
                'content_hash' => $comment->generateContentHash(),
            ]);

            $moderationStatus = 'pending';
            $moderationMessage = 'Commentaire ajouté.';

            if (!$fastDecision) {
                // Dispatch du job pour analyse IA
                ModerateCommentJob::dispatch($comment)->afterCommit();
                $moderationMessage = 'Commentaire ajouté, en cours d\'analyse.';
                $moderationStatus = 'pending';
            } else {
                // Audit log pour décision rapide
                $moderation->auditLogs()->create([
                    'action' => $fastDecision,
                    'actor_type' => 'system',
                    'payload' => [
                        'reason' => 'Fast moderation layer decision',
                        'rule' => $fastDecision === 'rejected' ? 'blocklist/rate_limit' : 'duplicate',
                    ],
                ]);

                if ($fastDecision === 'rejected') {
                    $moderationMessage = 'Votre commentaire a été rejeté car il enfreint les règles.';
                } elseif ($fastDecision === 'approved') {
                    $moderationMessage = 'Commentaire ajouté et approuvé automatiquement.';
                } else {
                    $moderationMessage = 'Commentaire ajouté, en cours de vérification.';
                }
                $moderationStatus = $fastDecision;
            }

            // ── Notifications ──────────────────────────────────────────────
            // Notification : commentaire sur post
            if ($post->user_id !== $user->id) {
                CommunityNotification::create([
                    'user_id'         => $post->user_id,
                    'actor_id'        => $user->id,
                    'type'            => 'comment',
                    'notifiable_type' => Comment::class,
                    'notifiable_id'   => $comment->id,
                    'message'         => $user->firstname . ' a commenté votre publication.',
                ]);
            }

            // Notification : réponse à un commentaire
            if ($request->parent_id) {
                $parent = Comment::find($request->parent_id);
                if ($parent && $parent->user_id !== $user->id) {
                    CommunityNotification::create([
                        'user_id'         => $parent->user_id,
                        'actor_id'        => $user->id,
                        'type'            => 'reply',
                        'notifiable_type' => Comment::class,
                        'notifiable_id'   => $comment->id,
                        'message'         => $user->firstname . ' a répondu à votre commentaire.',
                    ]);
                }
            }

            DB::commit();

            $comment->load(['author.profile', 'replies.author.profile', 'moderation']);

            return response()->json([
                'success' => true,
                'message' => $moderationMessage,
                'data'    => $this->formatComment($comment, $user->id),
                'moderation_status' => $moderationStatus,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur store comment: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Modifier un commentaire ───────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user = $request->user();

        if (!$user || $comment->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $comment->update(['content' => $request->content]);

        // Réinitialiser la modération
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

            // Relancer l'analyse
            ModerateCommentJob::dispatch($comment)->afterCommit();
        }

        $comment->load(['author.profile', 'moderation']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire modifié. Nouvelle analyse en cours.',
            'data'    => $this->formatComment($comment, $user->id),
        ]);
    }

    // ── Supprimer un commentaire ──────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user = $request->user();

        if (!$user || ($comment->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        // Décrémenter le compteur du post
        Post::where('id', $comment->post_id)->decrement('comments_count');

        // Supprimer la modération
        if ($comment->moderation) {
            $comment->moderation->delete();
        }

        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Commentaire supprimé.']);
    }

    // ── Like / Unlike un commentaire ──────────────────────────────────────────
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        // Vérifier si le commentaire est visible
        if (!$comment->isVisible()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce commentaire n\'est pas disponible.',
            ], 404);
        }

        $like = Like::where([
            'user_id'       => $user->id,
            'likeable_type' => Comment::class,
            'likeable_id'   => $comment->id,
        ])->first();

        if ($like) {
            $like->delete();
            $comment->decrement('likes_count');
            $liked = false;
        } else {
            Like::create([
                'user_id'       => $user->id,
                'likeable_type' => Comment::class,
                'likeable_id'   => $comment->id,
            ]);
            $comment->increment('likes_count');
            $liked = true;

            if ($comment->user_id !== $user->id) {
                CommunityNotification::create([
                    'user_id'         => $comment->user_id,
                    'actor_id'        => $user->id,
                    'type'            => 'like_comment',
                    'notifiable_type' => Comment::class,
                    'notifiable_id'   => $comment->id,
                    'message'         => $user->firstname . ' a aimé votre commentaire.',
                ]);
            }
        }

        return response()->json([
            'success'     => true,
            'liked'       => $liked,
            'likes_count' => $comment->fresh()->likes_count,
        ]);
    }

    // ── Commentaires en attente de modération pour l'utilisateur ─────────────
    public function pendingComments(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $comments = Comment::with(['author.profile', 'post', 'moderation'])
            ->where('user_id', $user->id)
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->latest()
            ->paginate(20);

        $comments->getCollection()->transform(fn($c) => $this->formatComment($c, $user->id));

        return response()->json(['success' => true, 'data' => $comments]);
    }

    // ── Format de réponse ─────────────────────────────────────────────────────
    private function formatComment(Comment $comment, ?int $authUserId): array
    {
        $data = [
            'id'          => $comment->id,
            'content'     => $comment->content,
            'post_id'     => $comment->post_id,
            'parent_id'   => $comment->parent_id,
            'author'      => [
                'id'        => $comment->author->id,
                'firstname' => $comment->author->firstname,
                'lastname'  => $comment->author->lastname,
                'avatar'    => $comment->author->avatar,
                'headline'  => $comment->author->profile?->headline,
            ],
            'likes_count' => $comment->likes_count,
            'is_liked'    => $authUserId ? $comment->isLikedBy($authUserId) : false,
            'replies'     => $comment->replies->map(fn($r) => $this->formatComment($r, $authUserId)),
            'created_at'  => $comment->created_at,
        ];

        // Ajouter les infos de modération si l'utilisateur est le propriétaire
        if ($authUserId && $comment->user_id === $authUserId) {
            $data['moderation'] = [
                'status' => $comment->moderation_status,
                'reason' => $comment->moderation_reason,
                'moderated_at' => $comment->moderated_at,
                'scores' => [
                    'toxicity' => $comment->toxicity_score,
                    'spam' => $comment->spam_score,
                    'hate' => $comment->hate_score,
                    'violence' => $comment->violence_score,
                ],
            ];
        }

        return $data;
    }
}
