<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Like;
use App\Models\CommunityNotification;
use App\Models\ModerationPost;
use App\Services\CloudinaryService;
use App\Services\Moderation\FastModerationLayer;
use App\Services\Moderation\SyncModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    protected FastModerationLayer $fastLayer;
    protected SyncModerationService $syncModeration;

    public function __construct(
        FastModerationLayer $fastLayer,
        SyncModerationService $syncModeration
    ) {
        $this->fastLayer = $fastLayer;
        $this->syncModeration = $syncModeration;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $posts = Post::with(['author.profile', 'sharedPost.author.profile', 'moderation'])
            ->feed($user->id)
            ->paginate(15);

        $posts->getCollection()->transform(fn($post) => $this->formatPost($post, $user->id));

        return response()->json(['success' => true, 'data' => $posts]);
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->input('q');

            if (!$query || strlen(trim($query)) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Requête trop courte (minimum 2 caractères)',
                ]);
            }

            $user = $request->user();
            $searchTerm = trim($query);

            $posts = Post::with(['author.profile', 'sharedPost.author.profile', 'moderation'])
                ->where(function ($q) use ($searchTerm) {
                    $q->where('content', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('title', 'LIKE', "%{$searchTerm}%");
                })
                ->whereHas('moderation', function ($q) {
                    $q->where('status', 'approved');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $posts->getCollection()->transform(
                fn($post) => $this->formatPost($post, $user?->id)
            );

            return response()->json([
                'success' => true,
                'data' => $posts,
                'query' => $searchTerm,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur recherche posts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('=== STORE POST ===', [
            'files' => array_keys($request->allFiles()),
            'content' => $request->content,
            'type' => $request->type,
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        if (!$user->canPublish()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas publier pour le moment. Trop de contenus rejetés ou en attente.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:20000',
            'type' => 'nullable|string|in:text,image,video,pdf,shared,announcement',
            'shared_post_id' => 'nullable|exists:posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $hasContent = !empty(trim($request->content ?? ''));
        $hasFiles = count($request->allFiles()) > 0;

        if (!$hasContent && !$hasFiles && !$request->shared_post_id) {
            return response()->json([
                'success' => false,
                'message' => 'La publication doit contenir du texte ou un fichier.',
            ], 422);
        }

        $moderationStatus = 'pending';
        $moderationMessage = 'Publication créée avec succès.';

        try {
            $post = DB::transaction(function () use ($request, $user) {
                $mediaUrls = [];
                $pdfFiles = [];
                $detectedType = 'text';

                $uploaded = [];
                $allFiles = $request->allFiles();

                foreach ($allFiles as $files) {
                    $files = is_array($files) ? $files : [$files];

                    foreach ($files as $file) {
                        $uploaded[] = $file;
                    }
                }

                foreach ($uploaded as $index => $file) {
                    if (!$file || !$file->isValid()) {
                        Log::warning("Fichier invalide à l'index $index");
                        continue;
                    }

                    $mimeType = $file->getMimeType();

                    if (!in_array($mimeType, self::ALLOWED_MIMES)) {
                        Log::warning("MIME non autorisé: $mimeType");
                        continue;
                    }

                    $isImage = str_starts_with($mimeType, 'image/');
                    $isVideo = str_starts_with($mimeType, 'video/');
                    $isPdf = $mimeType === 'application/pdf';

                    $maxBytes = $isVideo
                        ? 200 * 1024 * 1024
                        : ($isPdf ? 20 * 1024 * 1024 : 10 * 1024 * 1024);

                    if ($file->getSize() > $maxBytes) {
                        throw new \Exception("Fichier \"{$file->getClientOriginalName()}\" trop volumineux.");
                    }

                    $cloudinaryFolder = match (true) {
                        $isVideo => 'agripulse/community/videos',
                        $isPdf => 'agripulse/community/pdfs',
                        default => 'agripulse/community/posts',
                    };

                    $url = app(CloudinaryService::class)
                        ->uploadImageUrl($file, $cloudinaryFolder);

                    if ($isPdf) {
                        $pdfFiles[] = [
                            'url' => $url,
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'size_label' => $this->formatBytes($file->getSize()),
                            'pages' => null,
                        ];

                        if ($detectedType === 'text') {
                            $detectedType = 'pdf';
                        }
                    } else {
                        $mediaUrls[] = $url;

                        if ($detectedType === 'text') {
                            $detectedType = $isVideo ? 'video' : 'image';
                        }
                    }
                }

                $postType = $request->type ?? $detectedType;

                if ($postType === 'text' && (count($mediaUrls) > 0 || count($pdfFiles) > 0)) {
                    $postType = $detectedType;
                }

                $post = Post::create([
                    'user_id' => $user->id,
                    'content' => trim($request->content ?? ''),
                    'type' => $postType,
                    'media_urls' => $mediaUrls,
                    'pdf_files' => $pdfFiles,
                    'shared_post_id' => $request->shared_post_id,
                    'likes_count' => 0,
                    'comments_count' => 0,
                    'shares_count' => 0,
                ]);

                ModerationPost::create([
                    'post_id' => $post->id,
                    'status' => 'pending',
                    'content_hash' => $post->generateContentHash(),
                ]);

                return $post;
            });

            try {
                $fastDecision = $this->fastLayer->check($post->content, $user->id);

                if ($fastDecision !== null) {
                    $moderationStatus = $fastDecision;
                    $moderationMessage = $this->getModerationMessage($fastDecision);

                    $post->moderation->update([
                        'status' => $fastDecision,
                        'moderated_at' => now(),
                        'reason' => $fastDecision === 'rejected'
                            ? 'Contenu rejeté par les filtres automatiques'
                            : 'Contenu approuvé par les filtres automatiques',
                    ]);
                } else {
                    $moderationResult = $this->syncModeration->moderatePostSync($post);
                    $moderationStatus = $moderationResult['status'];
                    $moderationMessage = $this->getModerationMessage($moderationStatus);

                    $post->moderation->update([
                        'status' => $moderationStatus,
                        'toxicity_score' => $moderationResult['scores']['toxicity'] ?? 0,
                        'spam_score' => $moderationResult['scores']['spam'] ?? 0,
                        'hate_score' => $moderationResult['scores']['hate'] ?? 0,
                        'violence_score' => $moderationResult['scores']['violence'] ?? 0,
                        'reason' => $moderationResult['reason'] ?? null,
                        'moderated_at' => now(),
                        'result_raw' => $moderationResult,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erreur modération', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);

                $post->moderation->update([
                    'status' => 'review',
                    'reason' => 'Erreur technique, vérification manuelle requise',
                    'moderated_at' => now(),
                ]);

                $moderationStatus = 'review';
                $moderationMessage = 'Publication créée, en cours de vérification manuelle.';
            }

            $post->load(['author.profile', 'sharedPost.author.profile', 'moderation']);

            return response()->json([
                'success' => true,
                'message' => $moderationMessage,
                'data' => $this->formatPost($post, $user->id),
                'moderation_status' => $moderationStatus,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur store post: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $post = Post::with(['author.profile', 'sharedPost.author.profile', 'moderation'])
            ->findOrFail($id);

        if (!$post->isVisible() && $post->user_id !== $user?->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette publication n\'est pas disponible.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPost($post, $user?->id),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        if (!$user || $post->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:20000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $post->update([
                'content' => $request->content,
            ]);

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

            DB::commit();

            try {
                $moderationResult = $this->syncModeration->moderatePostSync($post);

                if ($post->moderation) {
                    $post->moderation->update([
                        'status' => $moderationResult['status'],
                        'toxicity_score' => $moderationResult['scores']['toxicity'] ?? 0,
                        'spam_score' => $moderationResult['scores']['spam'] ?? 0,
                        'hate_score' => $moderationResult['scores']['hate'] ?? 0,
                        'violence_score' => $moderationResult['scores']['violence'] ?? 0,
                        'reason' => $moderationResult['reason'] ?? null,
                        'moderated_at' => now(),
                        'result_raw' => $moderationResult,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erreur réanalyse', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);

                if ($post->moderation) {
                    $post->moderation->update([
                        'status' => 'review',
                        'reason' => 'Erreur technique, vérification manuelle requise',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Publication mise à jour.',
                'data' => $this->formatPost($post->fresh(['author.profile', 'moderation']), $user->id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        if (!$user || ($post->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if ($post->moderation) {
            $post->moderation->delete();
        }

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Publication supprimée.',
        ]);
    }

    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        if (!$post->isVisible()) {
            return response()->json(['success' => false, 'message' => 'Cette publication n\'est pas disponible.'], 404);
        }

        $like = Like::where([
            'user_id' => $user->id,
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ])->first();

        if ($like) {
            $like->delete();
            $post->decrement('likes_count');
            $liked = false;
        } else {
            Like::create([
                'user_id' => $user->id,
                'likeable_type' => Post::class,
                'likeable_id' => $post->id,
            ]);

            $post->increment('likes_count');
            $liked = true;

            if ($post->user_id !== $user->id) {
                CommunityNotification::create([
                    'user_id' => $post->user_id,
                    'actor_id' => $user->id,
                    'type' => 'like_post',
                    'notifiable_type' => Post::class,
                    'notifiable_id' => $post->id,
                    'message' => $user->firstname . ' a aimé votre publication.',
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $post->fresh()->likes_count,
        ]);
    }

    public function userPosts(Request $request, int $userId): JsonResponse
    {
        $user = $request->user();

        $posts = Post::with(['author.profile', 'sharedPost.author.profile', 'moderation'])
            ->where('user_id', $userId)
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->latest()
            ->paginate(12);

        $posts->getCollection()->transform(
            fn($post) => $this->formatPost($post, $user?->id)
        );

        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }

    public function reanalyze(Request $request, int $id): JsonResponse
    {
        $post = Post::findOrFail($id);
        $user = $request->user();

        if (!$user || ($post->user_id !== $user->id && !$user->isAdmin())) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if (!$post->moderation) {
            return response()->json(['success' => false, 'message' => 'Aucune modération trouvée.'], 404);
        }

        try {
            $moderationResult = $this->syncModeration->moderatePostSync($post);

            $post->moderation->update([
                'status' => $moderationResult['status'],
                'toxicity_score' => $moderationResult['scores']['toxicity'] ?? 0,
                'spam_score' => $moderationResult['scores']['spam'] ?? 0,
                'hate_score' => $moderationResult['scores']['hate'] ?? 0,
                'violence_score' => $moderationResult['scores']['violence'] ?? 0,
                'reason' => $moderationResult['reason'] ?? null,
                'moderated_at' => now(),
                'result_raw' => $moderationResult,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réanalyse effectuée avec succès.',
                'data' => [
                    'moderation' => [
                        'status' => $moderationResult['status'],
                        'reason' => $moderationResult['reason'] ?? null,
                        'scores' => $moderationResult['scores'] ?? [],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur réanalyse: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réanalyse: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' Mo';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' Ko';
        }

        return $bytes . ' o';
    }

    private function formatPost(Post $post, ?int $authUserId): array
    {
        $data = [
            'id' => $post->id,
            'content' => $post->content,
            'type' => $post->type,
            'media_urls' => $post->media_urls ?? [],
            'pdf_files' => $post->pdf_files ?? [],
            'author' => $this->formatUser($post->author),
            'shared_post' => $post->sharedPost
                ? $this->formatPost($post->sharedPost, $authUserId)
                : null,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'shares_count' => $post->shares_count,
            'is_liked' => $authUserId ? $post->isLikedBy($authUserId) : false,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'moderation_status' => $post->moderation_status,
        ];

        if ($authUserId && $post->user_id === $authUserId) {
            $data['moderation'] = [
                'status' => $post->moderation_status,
                'reason' => $post->moderation_reason,
                'moderated_at' => $post->moderated_at,
                'scores' => [
                    'toxicity' => $post->toxicity_score,
                    'spam' => $post->spam_score,
                    'hate' => $post->hate_score,
                    'violence' => $post->violence_score,
                ],
            ];
        }

        return $data;
    }

    private function formatUser($user): array
    {
        if (!$user) {
            return [
                'id' => null,
                'firstname' => 'Utilisateur',
                'lastname' => 'supprimé',
                'avatar' => null,
                'headline' => null,
                'role' => null,
            ];
        }

        return [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'avatar' => $user->profile?->avatar_url ?? $user->profile?->avatar,
            'headline' => $user->profile?->headline,
            'role' => $user->role,
        ];
    }

    private function getModerationMessage(string $status): string
    {
        return match ($status) {
            'approved' => '✅ Publication approuvée et visible immédiatement.',
            'review' => '🔍 Publication en cours de vérification manuelle.',
            'rejected' => '❌ Publication rejetée car elle enfreint les règles.',
            'pending' => '⏳ Publication en attente d\'analyse.',
            default => 'Publication créée.',
        };
    }
}
