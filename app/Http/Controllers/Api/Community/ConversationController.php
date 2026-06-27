<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private ConversationService $conversationService) {}

    /**
     * GET /api/ai/conversations
     * Liste des conversations de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = AiConversation::where('user_id', $user->id)
            ->withCount('messages')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json($conversations);
    }

    /**
     * GET /api/ai/conversations/{id}
     * Détail d'une conversation avec ses messages
     */
    public function show(string $id): JsonResponse
    {
        $conversation = AiConversation::with('messages')
            ->where('id', $id)
            ->firstOrFail();

        // Vérifier que l'utilisateur est propriétaire
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($conversation);
    }

    /**
     * DELETE /api/ai/conversations/{id}
     * Supprimer une conversation
     */
    public function destroy(string $id): JsonResponse
    {
        $conversation = AiConversation::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted successfully']);
    }
}
