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
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/avi',
        'application/pdf',
    ];

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

        Log::info('=== STORE POST ===', [
            'files'   => array_keys($request->allFiles()),
            'content' => $request->content,
            'type'    => $request->type,
        ]);

        // ── Validation minimale (pas de règle sur media) ──────────────────────
        $validator = Validator::make($request->all(), [
            'content'        => 'nullable|string|max:5000',
            'type'           => 'nullable|string|in:text,image,video,pdf,shared,announcement',
            'shared_post_id' => 'nullable|exists:posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── Au moins content ou un fichier ────────────────────────────────────
        $hasContent = !empty(trim($request->content ?? ''));
        $hasFiles   = count($request->allFiles()) > 0;

        if (!$hasContent && !$hasFiles && !$request->shared_post_id) {
            return response()->json([
                'success' => false,
                'message' => 'La publication doit contenir du texte ou un fichier.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $mediaUrls    = [];
            $pdfFiles     = [];
            $detectedType = 'text';

            // ── Collecter les fichiers ────────────────────────────────────────
            $uploaded = [];
            $allFiles = $request->allFiles();

            foreach ($allFiles as $key => $files) {
                $files = is_array($files) ? $files : [$files];
                foreach ($files as $file) {
                    $uploaded[] = $file;
                }
            }

            Log::info('Fichiers collectés:', ['count' => count($uploaded)]);

            // ── Uploader chaque fichier ───────────────────────────────────────
            foreach ($uploaded as $index => $file) {
                if (!$file || !$file->isValid()) {
                    Log::warning("Fichier invalide à l'index $index");
                    continue;
                }

                $mimeType = $file->getMimeType();
                Log::info("Fichier $index:", ['name' => $file->getClientOriginalName(), 'mime' => $mimeType, 'size' => $file->getSize()]);

                if (!in_array($mimeType, self::ALLOWED_MIMES)) {
                    Log::warning("MIME non autorisé: $mimeType");
                    continue;
                }

                $isImage = str_starts_with($mimeType, 'image/');
                $isVideo = str_starts_with($mimeType, 'video/');
                $isPdf   = $mimeType === 'application/pdf';

                // Vérification taille
                $maxBytes = $isVideo ? 200 * 1024 * 1024
                    : ($isPdf  ? 20  * 1024 * 1024
                        : 10  * 1024 * 1024);

                if ($file->getSize() > $maxBytes) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Fichier \"{$file->getClientOriginalName()}\" trop volumineux.",
                    ], 422);
                }

                $folder    = $isVideo ? 'community/videos'
                    : ($isPdf  ? 'community/pdfs'
                        : 'community/posts');

                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $filename  = uniqid() . '_' . time() . '_' . $index . '.' . $extension;
                $path      = $file->storeAs($folder, $filename, 'public');

                if (!$path) {
                    Log::error("Échec stockage fichier $index");
                    continue;
                }

                $url = asset('storage/' . $path);

                if ($isPdf) {
                    $pdfFiles[] = [
                        'url'        => $url,
                        'name'       => $file->getClientOriginalName(),
                        'size'       => $file->getSize(),
                        'size_label' => $this->formatBytes($file->getSize()),
                        'pages'      => null,
                    ];
                    if ($detectedType === 'text') $detectedType = 'pdf';
                } else {
                    $mediaUrls[] = $url;
                    if ($detectedType === 'text') {
                        $detectedType = $isVideo ? 'video' : 'image';
                    }
                }

                Log::info("Fichier uploadé:", ['url' => $url, 'type' => $detectedType]);
            }

            // ── Type final ────────────────────────────────────────────────────
            $postType = $request->type ?? $detectedType;
            if ($postType === 'text' && (count($mediaUrls) > 0 || count($pdfFiles) > 0)) {
                $postType = $detectedType;
            }

            // ── Création ──────────────────────────────────────────────────────
            $post = Post::create([
                'user_id'        => $request->user()->id,
                'content'        => trim($request->content ?? ''),
                'type'           => $postType,
                'media_urls'     => $mediaUrls,
                'pdf_files'      => $pdfFiles,
                'shared_post_id' => $request->shared_post_id,
                'likes_count'    => 0,
                'comments_count' => 0,
                'shares_count'   => 0,
            ]);

            DB::commit();

            $post->load(['author.profile', 'sharedPost.author']);

            Log::info('Post créé:', ['id' => $post->id, 'type' => $postType]);

            return response()->json([
                'success' => true,
                'message' => 'Publication créée avec succès.',
                'data'    => $this->formatPost($post, $request->user()->id),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur store post: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
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

        // Supprimer les fichiers physiques
        foreach (array_merge($post->media_urls ?? [], array_column($post->pdf_files ?? [], 'url')) as $url) {
            try {
                $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            } catch (\Exception $e) {
                Log::error('Erreur suppression fichier: ' . $e->getMessage());
            }
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) return round($bytes / (1024 * 1024), 1) . ' Mo';
        if ($bytes >= 1024)        return round($bytes / 1024, 1) . ' Ko';
        return $bytes . ' o';
    }

    private function formatPost(Post $post, int $authUserId): array
    {
        return [
            'id'             => $post->id,
            'content'        => $post->content,
            'type'           => $post->type,
            'media_urls'     => $post->media_urls ?? [],
            'pdf_files'      => $post->pdf_files  ?? [],
            'author'         => $this->formatUser($post->author),
            'shared_post'    => $post->sharedPost
                ? $this->formatPost($post->sharedPost, $authUserId)
                : null,
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
        if (!$user) {
            return [
                'id'        => null,
                'firstname' => 'Utilisateur',
                'lastname'  => 'supprimé',
                'avatar'    => null,
                'headline'  => null,
                'role'      => null,
            ];
        }

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
