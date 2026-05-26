<?php

namespace App\Http\Controllers\api\community;

use App\Http\Controllers\Controller;
use App\Models\{Follow, User, CommunityNotification};
use Illuminate\Http\{JsonResponse, Request};

class FollowController extends Controller
{
    // Suivre / Ne plus suivre
    public function toggle(Request $request, int $userId): JsonResponse
    {
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
    public function followers(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        $followers = Follow::with('follower.profile')
            ->where('following_id', $userId)
            ->latest()->paginate(20);

        return response()->json(['success' => true, 'data' => $followers]);
    }

    // Abonnements d'un utilisateur
    public function following(int $userId): JsonResponse
    {
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
