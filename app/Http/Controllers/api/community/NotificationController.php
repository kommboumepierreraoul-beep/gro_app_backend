<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\CommunityNotification;
use App\Models\User;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    // ────────────────────────────────────────────────────────────────────────────
    // COMMUNITY NOTIFICATIONS
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Liste des notifications communautaires
     */
    public function index(Request $request): JsonResponse
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Erreur index notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications'
            ], 500);
        }
    }

    /**
     * Notifications non lues
     */
    public function unread(Request $request): JsonResponse
    {
        try {
            $notifications = CommunityNotification::with('actor.profile')
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur unread notifications: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications non lues'
            ], 500);
        }
    }

    /**
     * Nombre de notifications non lues
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = CommunityNotification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
                'unread_count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur unreadCount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage'
            ], 500);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        try {
            $notif = CommunityNotification::where('user_id', $request->user()->id)->findOrFail($id);
            $notif->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur markRead: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage'
            ], 500);
        }
    }

    /**
     * Tout marquer comme lu
     */
    public function markAllRead(Request $request): JsonResponse
    {
        try {
            CommunityNotification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur markAllRead: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage'
            ], 500);
        }
    }

    /**
     * Supprimer une notification
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            CommunityNotification::where('user_id', $request->user()->id)->findOrFail($id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Supprimer toutes les notifications
     */
    public function clearAll(Request $request): JsonResponse
    {
        try {
            CommunityNotification::where('user_id', $request->user()->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications supprimées'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur clearAll: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // MISSION NOTIFICATIONS
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Liste des notifications des missions
     */
    public function missionIndex(Request $request): JsonResponse
    {
        try {
            $notifications = CommunityNotification::with('actor.profile')
                ->where('user_id', $request->user()->id)
                ->where('type', 'like', 'mission_%')
                ->orWhere('type', 'review_request')
                ->orWhere('type', 'mission_report')
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'unread_count' => CommunityNotification::where('user_id', $request->user()->id)
                    ->where('is_read', false)
                    ->where(function ($query) {
                        $query->where('type', 'like', 'mission_%')
                            ->orWhere('type', 'review_request')
                            ->orWhere('type', 'mission_report');
                    })
                    ->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur missionIndex: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des notifications'
            ], 500);
        }
    }

    /**
     * Notifications de missions non lues
     */
    public function missionUnread(Request $request): JsonResponse
    {
        try {
            $notifications = CommunityNotification::with('actor.profile')
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->where(function ($query) {
                    $query->where('type', 'like', 'mission_%')
                        ->orWhere('type', 'review_request')
                        ->orWhere('type', 'mission_report');
                })
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur missionUnread: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement'
            ], 500);
        }
    }

    /**
     * Nombre de notifications de missions non lues
     */
    public function missionUnreadCount(Request $request): JsonResponse
    {
        try {
            $count = CommunityNotification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->where(function ($query) {
                    $query->where('type', 'like', 'mission_%')
                        ->orWhere('type', 'review_request')
                        ->orWhere('type', 'mission_report');
                })
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur missionUnreadCount: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du comptage'
            ], 500);
        }
    }

    /**
     * Types de notifications disponibles
     */
    public function types(Request $request): JsonResponse
    {
        try {
            $types = [
                'community' => [
                    'like_post',
                    'like_comment',
                    'comment',
                    'reply',
                    'follow',
                    'share',
                    'mention',
                    'announcement',
                ],
                'missions' => [
                    'new_mission',
                    'new_application',
                    'application_accepted',
                    'application_rejected',
                    'application_withdrawn',
                    'mission_reminder',
                    'mission_updated',
                    'mission_completed',
                    'mission_filled',
                    'mission_cancelled',
                    'review_request',
                    'mission_report',
                ],
            ];

            return response()->json([
                'success' => true,
                'types' => $types,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur types: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des types'
            ], 500);
        }
    }
}
