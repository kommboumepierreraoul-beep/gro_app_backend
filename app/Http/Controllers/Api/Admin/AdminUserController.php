<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // Tous les utilisateurs
    public function allUsers(Request $request)
    {
        $limit = $request->query('limit', 20);
        $page = $request->query('page', 1);

        $users = User::with('shop:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ]
        ]);
    }

    // Suspendre un utilisateur
    public function suspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'suspended']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur suspendu'
        ]);
    }

    // Réactiver un utilisateur
    public function unsuspendUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur réactivé'
        ]);
    }

    // Supprimer un utilisateur
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé'
        ]);
    }
}
