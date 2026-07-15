<?php

namespace App\Http\Controllers\api\community;

use App\Http\Controllers\Controller;
use App\Models\{Follow, User, CommunityNotification};
use Illuminate\Http\{JsonResponse, Request};

class FollowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = trim((string) $request->get('search', ''));
        $followingIds = Follow::where('follower_id', $user->id)->pluck('following_id')->all();

        $users = User::with('profile')
            ->withCount(['posts', 'followers', 'following'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', function ($profile) use ($search) {
                            $profile->where('headline', 'like', "%{$search}%")
                                ->orWhere('bio', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 24));

        $users->getCollection()->transform(fn ($u) => [
            'id' => $u->id,
            'firstname' => $u->firstname,
            'lastname' => $u->lastname,
            'email' => $u->email,
            'avatar' => $u->profile?->avatar,
            'role' => $u->role,
            'status' => $u->status,
            'headline' => $u->profile?->headline,
            'bio' => $u->profile?->bio,
            'location' => $u->profile?->location,
            'followers_count' => $u->followers_count,
            'following_count' => $u->following_count,
            'posts_count' => $u->posts_count,
            'is_following' => in_array($u->id, $followingIds, true),
            'is_me' => $u->id === $user->id,
            'is_verified' => $u->email_verified_at !== null,
            'created_at' => $u->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // Suivre / Ne plus suivre
    public function toggle(Request $request, $userId): JsonResponse  // ✅ Supprimer le typage int
    {
        // ✅ Conversion en int
        $userId = (int) $userId;

        $target = User::findOrFail($userId);
        $user   = $request->user();

        if ($user->id === $userId) {
            return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas vous suivre vous-même.'], 422);
        }

        $follow = Follow::where(['follower_id' => $user->id, 'following_id' => $userId])->first();

        if ($follow) {
            $follow->delete();
            $isFollowing = false;
        } else {
            Follow::create(['follower_id' => $user->id, 'following_id' => $userId]);
            $isFollowing = true;

            CommunityNotification::create([
                'user_id'  => $userId,
                'actor_id' => $user->id,
                'type'     => 'follow',
                'message'  => $user->firstname . ' a commencé à vous suivre.',
            ]);
        }

        return response()->json([
            'success'          => true,
            'is_following'     => $isFollowing,
            'followers_count'  => Follow::where('following_id', $userId)->count(),
        ]);
    }

    // Abonnés d'un utilisateur
    public function followers($userId): JsonResponse  // ✅ Supprimer le typage int
    {
        // ✅ Conversion en int
        $userId = (int) $userId;

        User::findOrFail($userId);
        $followers = Follow::with('follower.profile')
            ->where('following_id', $userId)
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $followers]);
    }

    // Abonnements d'un utilisateur
    public function following($userId): JsonResponse  // ✅ Supprimer le typage int
    {
        // ✅ Conversion en int
        $userId = (int) $userId;

        User::findOrFail($userId);
        $following = Follow::with('following.profile')
            ->where('follower_id', $userId)
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $following]);
    }

    // Suggestions de personnes à suivre
    public function suggestions(Request $request): JsonResponse
    {
        $user          = $request->user();
        $followingIds  = Follow::where('follower_id', $user->id)->pluck('following_id');
        $excludeIds    = $followingIds->push($user->id);

        $suggestions = User::with('profile')
            ->whereNotIn('id', $excludeIds)
            ->inRandomOrder()
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'id'              => $u->id,
                'firstname'       => $u->firstname,
                'lastname'        => $u->lastname,
                'avatar'          => $u->avatar,
                'headline'        => $u->profile?->headline,
                'followers_count' => Follow::where('following_id', $u->id)->count(),
            ]);

        return response()->json(['success' => true, 'data' => $suggestions]);
    }
}
