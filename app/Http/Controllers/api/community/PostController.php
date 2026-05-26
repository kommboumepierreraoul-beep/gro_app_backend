<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Like;
use App\Models\CommunityNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class PostController extends Controller
{
    // ── Feed paginé ───────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $posts = Post::with(['author.profile', 'sharedPost.author'])
            ->feed($user->id)
            ->paginate(15);

        $posts->getCollection()->transform(fn($post) => $this->formatPost($post, $user->id));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    // ── Créer un post ─────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        Log::info('FILES:', $request->allFiles());
        $validator = Validator::make($request->all(), [
            'content'        => 'required|string|max:5000',
            'type'           => 'nullable|in:text,image,video,announcement,shared',
            'media'          => 'nullable|array|max:5',
            'media.*'        => 'file|mimes:jpg,jpeg,png,gif,mp4,mov|max:51200',
            'shared_post_id' => 'nullable|exists:posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $mediaUrls = [];

            // ✅ UPLOAD FILES
            if ($request->hasFile('media')) {

                foreach ($request->file('media') as $file) {

                    $path = $file->store('community/posts', 'public');

                    $mediaUrls[] = Storage::url($path);
                }
            }

            // ✅ CREATE POST
            $post = Post::create([
                'user_id'        => $request->user()->id,
                'content'        => $request->content,
                'type'           => $request->type ?? 'text',
                'media_urls'     => $mediaUrls,
                'shared_post_id' => $request->shared_post_id,
            ]);

            DB::commit();

            // load relations
            $post->load(['author.profile', 'sharedPost.author']);

            return response()->json([
                'success' => true,
                'message' => 'Publication créée avec succès.',
                'data'    => $this->formatPost($post, $request->user()->id),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ── Voir un post ──────────────────────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $post = Post::with(['author.profile', 'sharedPost.author'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatPost($post, $request->user()->id),
        ]);
    }

    // ── Modifier un post ──────────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);

        if ($post->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $post->update(['content' => $request->content]);

        return response()->json([
            'success' => true,
            'message' => 'Publication mise à jour.',
            'data'    => $this->formatPost($post->fresh(['author.profile']), $request->user()->id),
        ]);
    }

    // ── Supprimer un post ─────────────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $post->delete();

        return response()->json(['success' => true, 'message' => 'Publication supprimée.']);
    }

    // ── Like / Unlike ─────────────────────────────────────────────────────────
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        $like = Like::where([
            'user_id'       => $user->id,
            'likeable_type' => Post::class,
            'likeable_id'   => $post->id,
        ])->first();

        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            Like::create([
                'user_id'       => $user->id,
                'likeable_type' => Post::class,
                'likeable_id'   => $post->id,
            ]);
            $post->increment('likes_count');
            $liked = true;

            // Notifier l'auteur (sauf si c'est lui-même)
            if ($post->user_id !== $user->id) {
                CommunityNotification::create([
                    'user_id'         => $post->user_id,
                    'actor_id'        => $user->id,
                    'type'            => 'like_post',
                    'notifiable_type' => Post::class,
                    'notifiable_id'   => $post->id,
                    'message'         => $user->firstname . ' a aimé votre publication.',
                ]);
            }
        }

        return response()->json([
            'success'     => true,
            'liked'       => $liked,
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }

    // ── Posts d'un utilisateur ────────────────────────────────────────────────
    public function userPosts(Request $request, int $userId): JsonResponse
    {
        $posts = Post::with(['author.profile', 'sharedPost.author'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(12);

        $posts->getCollection()->transform(fn($post) => $this->formatPost($post, $request->user()->id));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    // ── Format de réponse unifié ──────────────────────────────────────────────
    private function formatPost(Post $post, int $authUserId): array
    {
        return [
            'id'             => $post->id,
            'content'        => $post->content,
            'type'           => $post->type,
            'media_urls'     => $post->media_urls ?? [],
            'author'         => $this->formatUser($post->author),
            'shared_post'    => $post->sharedPost ? $this->formatPost($post->sharedPost, $authUserId) : null,
            'likes_count'    => $post->likes_count,
            'comments_count' => $post->comments_count,
            'shares_count'   => $post->shares_count,
            'is_liked'       => $post->isLikedBy($authUserId),
            'created_at'     => $post->created_at,
            'updated_at'     => $post->updated_at,
        ];
    }

    private function formatUser($user): array
    {
        return [
            'id'        => $user->id,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'avatar'    => $user->avatar,
            'headline'  => $user->profile?->headline,
            'role'      => $user->role,
        ];
    }
}
