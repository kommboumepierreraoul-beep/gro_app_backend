<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\{Follow, User, UserProfile};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Validator};

class ProfileController extends Controller
{
    // Profil d'un utilisateur
    public function show(Request $request, int $userId): JsonResponse
    {
        $target = User::with('profile')->findOrFail($userId);
        $authId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data' => $this->formatProfile($target, $authId),
        ]);
    }

    // Mon profil
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'data' => $this->formatProfile($user, $user->id),
        ]);
    }

    // Mettre à jour le profil étendu
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'headline' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1000',
            'location' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url',
            'avatar' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
            'banner' => 'sometimes|nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Mettre à jour avatar
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('community/avatars', 'public');
            $user->update(['avatar' => asset('storage/' . $path)]);
        }

        // Mettre à jour firstname/lastname
        $user->update($request->only('firstname', 'lastname'));

        // Mettre à jour ou créer le profil étendu
        $profileData = $request->only('headline', 'bio', 'location', 'website');

        if ($request->hasFile('banner')) {
            $path = $request->file('banner')->store('community/banners', 'public');
            $profileData['banner'] = asset('storage/' . $path);
        }

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'data' => $this->formatProfile($user, $user->id),
        ]);
    }

    // Recherche d'utilisateurs
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $users = User::with('profile')
            ->where(
                fn($q) =>
                $q->where('firstname', 'like', "%{$query}%")
                    ->orWhere('lastname', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
            )
            ->limit(10)
            ->get()
            ->map(fn($u) => $this->formatProfile($u, $request->user()->id));

        return response()->json(['success' => true, 'data' => $users]);
    }

    private function formatProfile(User $user, int $authId): array
    {
        return [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'headline' => $user->profile?->headline,
            'bio' => $user->profile?->bio,
            'location' => $user->profile?->location,
            'website' => $user->profile?->website,
            'banner' => $user->profile?->banner,
            'followers_count' => Follow::where('following_id', $user->id)->count(),
            'following_count' => Follow::where('follower_id', $user->id)->count(),
            'posts_count' => $user->posts()->count(),
            'is_following' => $authId !== $user->id
                ? Follow::where(['follower_id' => $authId, 'following_id' => $user->id])->exists()
                : false,
            'is_me' => $authId === $user->id,
            'created_at' => $user->created_at,
        ];
    }
}
