<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityNotification;
use Illuminate\Http\{JsonResponse, Request};

class NotificationController extends Controller
{
    // Liste des notifications
    public function index(Request $request): JsonResponse
    {
        $notifications = CommunityNotification::with('actor.profile')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => CommunityNotification::where('user_id', $request->user()->id)
                ->where('is_read', false)->count(),
        ]);
    }

    // Marquer une notification comme lue
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = CommunityNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // Tout marquer comme lu
    public function markAllRead(Request $request): JsonResponse
    {
        CommunityNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues.']);
    }

    // Supprimer une notification
    public function destroy(Request $request, int $id): JsonResponse
    {
        CommunityNotification::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
