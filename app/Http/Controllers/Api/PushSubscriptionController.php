<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    // Enregistrer un abonnement push
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
            'p256dh'   => 'required|string',
            'auth'     => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['user_id' => Auth::id(), 'endpoint' => $request->endpoint],
            ['p256dh' => $request->p256dh, 'auth' => $request->auth]
        );

        return response()->json(['status' => 'ok']);
    }

    // Supprimer un abonnement (désinscription)
    public function destroy(Request $request)
    {
        PushSubscription::where('user_id', Auth::id())
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    // Clé publique VAPID pour le frontend
    public function vapidKey()
    {
        if (!config('services.vapid.public_key')) {
            return response()->json([
                'message' => 'Cle publique VAPID non configuree',
            ], 503);
        }

        return response()->json([
            'public_key' => config('services.vapid.public_key'),
        ]);
    }
}
