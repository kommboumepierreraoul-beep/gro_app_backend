<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->take(30)->get()->map(function ($n) {
            return [
                'id'         => $n->id,
                'title'      => $n->data['title'] ?? '',
                'message'    => $n->data['message'] ?? '',
                'type'       => $n->data['type'] ?? '',
                'url'        => $n->data['url'] ?? null,
                'read'       => !is_null($n->read_at),
                'created_at' => $n->created_at,
            ];
        });
        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(string $id)
    {
        $n = Auth::user()->notifications()->where('id', $id)->first();
        if ($n) $n->markAsRead();
        return response()->json(['status' => 'ok']);
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 'ok']);
    }

    public function unreadCount()
    {
        return response()->json([
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
