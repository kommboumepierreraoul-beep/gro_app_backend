<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\{Announcement, Like};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Validator};

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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:255',
            'content'      => 'required|string|max:5000',
            'category'     => 'required|in:job,event,news,training,other',
            'cover_image'  => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
            'expires_at'   => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $coverUrl = null;
        if ($request->hasFile('cover_image')) {
            $path     = $request->file('cover_image')->store('community/announcements', 'public');
            $coverUrl = Storage::url($path);
        }

        $announcement = Announcement::create([
            'user_id'     => $request->user()->id,
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
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::with('author.profile')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $this->format($announcement, $request->user()->id)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);

        if ($announcement->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $announcement->delete();
        return response()->json(['success' => true, 'message' => 'Annonce supprimée.']);
    }

    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $user         = $request->user();

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
            Like::create(['user_id' => $user->id, 'likeable_type' => Announcement::class, 'likeable_id' => $announcement->id]);
            $announcement->increment('likes_count');
            $liked = true;
        }

        return response()->json(['success' => true, 'liked' => $liked, 'likes_count' => $announcement->fresh()->likes_count]);
    }

    private function format(Announcement $a, int $userId): array
    {
        return [
            'id'           => $a->id,
            'title'        => $a->title,
            'content'      => $a->content,
            'category'     => $a->category,
            'cover_image'  => $a->cover_image,
            'author'       => ['id' => $a->author->id, 'firstname' => $a->author->firstname, 'lastname' => $a->author->lastname, 'avatar' => $a->author->avatar],
            'likes_count'  => $a->likes_count,
            'is_liked'     => $a->isLikedBy($userId),
            'expires_at'   => $a->expires_at,
            'created_at'   => $a->created_at,
        ];
    }
}