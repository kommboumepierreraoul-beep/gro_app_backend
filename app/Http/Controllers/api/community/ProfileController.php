<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\{Follow, User, UserProfile};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Validator, Log, DB};

class ProfileController extends Controller
{
    // Afficher le profil d'un utilisateur
    public function show(Request $request, int $userId): JsonResponse
    {
        try {
            $target = User::with('profile')->findOrFail($userId);
            $authId = $request->user()->id;

            return response()->json([
                'success' => true,
                'data' => $this->formatProfile($target, $authId),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
    }

    // Afficher mon profil
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'data' => $this->formatProfile($user, $user->id),
        ]);
    }

    // Mettre à jour le profil
    public function update(Request $request): JsonResponse
    {
        Log::info('Profile update - Files:', array_keys($request->allFiles()));
        Log::info('Profile update - Data:', $request->except('avatar', 'banner'));

        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'headline' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string|max:1000',
            'location' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'avatar' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'banner' => 'sometimes|nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $user = $request->user();

            // Récupérer ou créer le profil
            $profile = UserProfile::firstOrCreate(
                ['user_id' => $user->id],
                []
            );

            $profileData = [];

            // 🔥 GESTION DE L'AVATAR
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');

                Log::info('Avatar file received:', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType()
                ]);

                // Supprimer l'ancien avatar
                if ($profile->avatar) {
                    $oldPath = str_replace('/storage/', '', parse_url($profile->avatar, PHP_URL_PATH));
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                // Générer un nom unique
                $extension = $file->getClientOriginalExtension();
                $filename = 'avatar_' . $user->id . '_' . time() . '.' . $extension;
                $path = $file->storeAs('community/avatars', $filename, 'public');

                if ($path) {
                    $profileData['avatar'] = asset('storage/' . $path);
                    Log::info('Avatar saved:', ['url' => $profileData['avatar']]);
                }
            }

            // 🔥 GESTION DE LA BANNIÈRE
            if ($request->hasFile('banner')) {
                $file = $request->file('banner');

                Log::info('Banner file received:', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType()
                ]);

                // Supprimer l'ancienne bannière
                if ($profile->banner) {
                    $oldPath = str_replace('/storage/', '', parse_url($profile->banner, PHP_URL_PATH));
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                // Générer un nom unique
                $extension = $file->getClientOriginalExtension();
                $filename = 'banner_' . $user->id . '_' . time() . '.' . $extension;
                $path = $file->storeAs('community/banners', $filename, 'public');

                if ($path) {
                    $profileData['banner'] = asset('storage/' . $path);
                    Log::info('Banner saved:', ['url' => $profileData['banner']]);
                }
            }

            // Ajouter les autres champs du profil
            if ($request->has('headline')) $profileData['headline'] = $request->headline;
            if ($request->has('bio')) $profileData['bio'] = $request->bio;
            if ($request->has('location')) $profileData['location'] = $request->location;
            if ($request->has('website')) $profileData['website'] = $request->website;

            // Mettre à jour firstname/lastname dans la table users
            if ($request->has('firstname') || $request->has('lastname')) {
                $user->update($request->only('firstname', 'lastname'));
            }

            // Mettre à jour le profil
            if (!empty($profileData)) {
                $profile->update($profileData);
            }

            DB::commit();

            // Recharger le user avec son profil
            $user->refresh();
            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès.',
                'data' => $this->formatProfile($user, $user->id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    // Supprimer l'avatar
    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = UserProfile::where('user_id', $user->id)->first();

            if ($profile && $profile->avatar) {
                // Supprimer le fichier
                $oldPath = str_replace('/storage/', '', parse_url($profile->avatar, PHP_URL_PATH));
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                // Supprimer l'URL de la base
                $profile->update(['avatar' => null]);

                $user->load('profile');

                return response()->json([
                    'success' => true,
                    'message' => 'Avatar supprimé avec succès.',
                    'data' => $this->formatProfile($user, $user->id)
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Aucun avatar à supprimer.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    // Supprimer la bannière
    public function deleteBanner(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = UserProfile::where('user_id', $user->id)->first();

            if ($profile && $profile->banner) {
                // Supprimer le fichier
                $oldPath = str_replace('/storage/', '', parse_url($profile->banner, PHP_URL_PATH));
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                // Supprimer l'URL de la base
                $profile->update(['banner' => null]);

                $user->load('profile');

                return response()->json([
                    'success' => true,
                    'message' => 'Bannière supprimée avec succès.',
                    'data' => $this->formatProfile($user, $user->id)
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Aucune bannière à supprimer.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
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

    // Formatage du profil
    private function formatProfile(User $user, int $authId): array
    {
        return [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'avatar' => $user->profile?->avatar,
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
