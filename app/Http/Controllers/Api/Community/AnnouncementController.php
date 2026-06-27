<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\{Announcement, Like};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Validator, Log};

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with('author.profile')->active()->latest();

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $announcements = $query->paginate(12);
        $userId = $request->user()->id;

        $announcements->getCollection()->transform(fn($a) => $this->format($a, $userId));

        return response()->json(['success' => true, 'data' => $announcements]);
    }

    public function latest(Request $request): JsonResponse
    {
        $limit = $request->limit ?? 3;

        $announcements = Announcement::with('author.profile')
            ->active()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->limit($limit)
            ->get();

        $userId = $request->user()->id;
        $announcements->transform(fn($a) => $this->format($a, $userId));

        return response()->json(['success' => true, 'data' => $announcements]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title'        => 'required|string|max:255',
                'content'      => 'required|string|max:5000',
                'category'     => 'required|in:job,event,news,training,other',
                'cover_image'  => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm|max:20480',
                'expires_at'   => 'nullable|date|after:today',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $coverUrl = null;

            if ($request->hasFile('cover_image')) {
                $file = $request->file('cover_image');
                $mimeType = $file->getMimeType();
                $isVideo = str_starts_with($mimeType, 'video/');

                // Dossier en fonction du type
                $folder = $isVideo ? 'community/announcements/videos' : 'community/announcements/images';

                // Générer un nom unique
                $extension = $file->getClientOriginalExtension();
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $path = $file->storeAs($folder, $filename, 'public');
                $coverUrl = Storage::url($path);

                Log::info('Fichier uploadé', [
                    'type' => $isVideo ? 'video' : 'image',
                    'size' => $file->getSize(),
                    'path' => $path
                ]);
            }

            $announcement = Announcement::create([
                'user_id'     => $request->user()->id,
                'type'        => 'announcement',
                'title'       => $request->title,
                'content'     => $request->content,
                'category'    => $request->category,
                'cover_image' => $coverUrl,
                'expires_at'  => $request->expires_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Annonce créée avec succès.',
                'data'    => $this->format($announcement->load('author.profile'), $request->user()->id),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur création annonce: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::with('author.profile')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $this->format($announcement, $request->user()->id)
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $announcement = Announcement::findOrFail($id);

            if ($announcement->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title'        => 'sometimes|required|string|max:255',
                'content'      => 'sometimes|required|string|max:5000',
                'category'     => 'sometimes|required|in:job,event,news,training,other',
                'cover_image'  => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,mkv,webm|max:20480',
                'expires_at'   => 'nullable|date|after:today',
                'remove_media' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Supprimer le média existant
            if ($request->remove_media === 'true' || $request->remove_media === true) {
                if ($announcement->cover_image) {
                    $oldPath = str_replace('/storage/', '', $announcement->cover_image);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                        Log::info('Fichier supprimé: ' . $oldPath);
                    }
                }
                $announcement->cover_image = null;
            }

            // Upload nouveau fichier
            if ($request->hasFile('cover_image')) {
                // Supprimer l'ancien fichier
                if ($announcement->cover_image && !$request->remove_media) {
                    $oldPath = str_replace('/storage/', '', $announcement->cover_image);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $file = $request->file('cover_image');
                $mimeType = $file->getMimeType();
                $isVideo = str_starts_with($mimeType, 'video/');
                $folder = $isVideo ? 'community/announcements/videos' : 'community/announcements/images';

                $extension = $file->getClientOriginalExtension();
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $path = $file->storeAs($folder, $filename, 'public');
                $announcement->cover_image = Storage::url($path);
            }

            // Mettre à jour les champs texte
            if ($request->has('title')) $announcement->title = $request->title;
            if ($request->has('content')) $announcement->content = $request->content;
            if ($request->has('category')) $announcement->category = $request->category;
            if ($request->has('expires_at')) $announcement->expires_at = $request->expires_at;

            $announcement->save();

            return response()->json([
                'success' => true,
                'message' => 'Annonce mise à jour avec succès.',
                'data'    => $this->format($announcement->load('author.profile'), $request->user()->id),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour annonce: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $announcement = Announcement::findOrFail($id);

            if ($announcement->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
                return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
            }

            // Supprimer le fichier associé
            if ($announcement->cover_image) {
                $path = str_replace('/storage/', '', $announcement->cover_image);
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    Log::info('Fichier supprimé: ' . $path);
                }
            }

            $announcement->delete();

            return response()->json(['success' => true, 'message' => 'Annonce supprimée avec succès.']);
        } catch (\Exception $e) {
            Log::error('Erreur suppression annonce: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $user = $request->user();

        $like = Like::where([
            'user_id'       => $user->id,
            'likeable_type' => Announcement::class,
            'likeable_id'   => $announcement->id,
        ])->first();

        if ($like) {
            $like->delete();
            $announcement->decrement('likes_count');
            $liked = false;
        } else {
            Like::create([
                'user_id' => $user->id,
                'likeable_type' => Announcement::class,
                'likeable_id' => $announcement->id
            ]);
            $announcement->increment('likes_count');
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $announcement->fresh()->likes_count
        ]);
    }

    private function format(Announcement $a, int $userId): array
    {
        // Détecter le type de média depuis l'URL
        $mediaType = 'image';
        if ($a->cover_image) {
            $extension = strtolower(pathinfo($a->cover_image, PATHINFO_EXTENSION));
            $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
            if (in_array($extension, $videoExtensions)) {
                $mediaType = 'video';
            }
        }

        return [
            'id'           => $a->id,
            'title'        => $a->title,
            'content'      => $a->content,
            'category'     => $a->category,
            'cover_image'  => $a->cover_image,
            'media_type'   => $mediaType,
            'author'       => [
                'id'        => $a->author->id,
                'firstname' => $a->author->firstname,
                'lastname'  => $a->author->lastname,
                'avatar'    => $a->author->avatar,
            ],
            'likes_count'  => $a->likes_count,
            'is_liked'     => $a->isLikedBy($userId),
            'expires_at'   => $a->expires_at,
            'created_at'   => $a->created_at,
        ];
    }
}
