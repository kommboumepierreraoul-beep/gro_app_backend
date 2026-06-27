<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\CommunityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    // ── Commentaires d'un post ────────────────────────────────────────────────
    public function index(Request $request, int $postId): JsonResponse
    {
        Post::findOrFail($postId);

        $comments = Comment::with(['author.profile', 'replies.author.profile'])
            ->where('post_id', $postId)
            ->whereNull('parent_id')   // uniquement les commentaires racine
            ->latest()
            ->paginate(20);

        $userId = $request->user()->id;
        $comments->getCollection()->transform(fn($c) => $this->formatComment($c, $userId));

        return response()->json(['success' => true, 'data' => $comments]);
    }

    // ── Ajouter un commentaire ────────────────────────────────────────────────
    public function store(Request $request, int $postId): JsonResponse
    {
        $post = Post::findOrFail($postId);

        $validator = Validator::make($request->all(), [
            'content'   => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $comment = Comment::create([
            'post_id'   => $postId,
            'user_id'   => $request->user()->id,
            'parent_id' => $request->parent_id,
            'content'   => $request->content,
        ]);

        // Incrémenter le compteur du post
        $post->increment('comments_count');

        $user = $request->user();

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

        $comment->load(['author.profile', 'replies.author.profile']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté.',
            'data'    => $this->formatComment($comment, $user->id),
        ], 201);
    }

    // ── Modifier un commentaire ───────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $comment->update(['content' => $request->content]);
        $comment->load(['author.profile']);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire modifié.',
            'data'    => $this->formatComment($comment, $request->user()->id),
        ]);
    }

    // ── Supprimer un commentaire ──────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user    = $request->user();

        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        // Décrémenter le compteur du post
        Post::where('id', $comment->post_id)->decrement('comments_count');

        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Commentaire supprimé.']);
    }

    // ── Like / Unlike un commentaire ──────────────────────────────────────────
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $user    = $request->user();

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

    // ── Format de réponse ─────────────────────────────────────────────────────
    private function formatComment(Comment $comment, int $authUserId): array
    {
        return [
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
            'is_liked'    => $comment->isLikedBy($authUserId),
            'replies'     => $comment->replies->map(fn($r) => $this->formatComment($r, $authUserId)),
            'created_at'  => $comment->created_at,
        ];
    }
}
