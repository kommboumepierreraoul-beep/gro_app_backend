<?php

namespace App\Http\Controllers\Api\AI;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\AI\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $query = $user->aiConversations()
                ->withCount('messages')
                ->orderBy('updated_at', 'desc');

            if ($search = $request->input('search')) {
                $query->where('title', 'LIKE', "%{$search}%");
            }

            if ($contextType = $request->input('context_type')) {
                $query->where('context_type', $contextType);
            }

            $conversations = $query->paginate(
                $request->input('per_page', 20)
            );

            return response()->json([
                'data' => $conversations->items(),
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
                'per_page' => $conversations->perPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Conversation index error', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Impossible de récupérer les conversations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $conversation = $this->conversationService->getConversation(
                $user->id,
                $id
            );

            if (!$conversation) {
                return response()->json([
                    'error' => 'Conversation non trouvée',
                ], 404);
            }

            Gate::authorize('view', $conversation);

            $formatted = [
                'id' => $conversation->id,
                'title' => $conversation->title ?? 'Nouvelle conversation',
                'user_id' => $conversation->user_id,
                'model' => $conversation->model,
                'context_type' => $conversation->context_type,
                'context_id' => $conversation->context_id,
                'total_tokens' => $conversation->total_tokens,
                'message_count' => $conversation->message_count,
                'created_at' => $conversation->created_at?->toISOString(),
                'updated_at' => $conversation->updated_at?->toISOString(),
                'meta' => $conversation->meta,
                'messages' => $conversation->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'tokens' => $message->tokens,
                        'position' => $message->position,
                        'in_context_window' => (bool) $message->in_context_window,
                        'created_at' => $message->created_at?->toISOString(),
                    ];
                }),
            ];

            return response()->json($formatted);
        } catch (\Exception $e) {
            Log::error('Conversation show error', [
                'user_id' => $request->user()?->id,
                'conversation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'error' => 'Conversation non trouvée',
                ], 404);
            }

            return response()->json([
                'error' => 'Impossible de récupérer la conversation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $conversation = $user->aiConversations()
                ->where('id', $id)
                ->firstOrFail();

            Gate::authorize('delete', $conversation);

            $deleted = $this->conversationService->deleteConversation(
                $user->id,
                $id
            );

            if (!$deleted) {
                return response()->json([
                    'error' => 'Impossible de supprimer la conversation',
                ], 500);
            }

            return response()->json([
                'message' => 'Conversation supprimée avec succès',
                'conversation_id' => $id,
            ]);
        } catch (\Exception $e) {
            Log::error('Conversation destroy error', [
                'user_id' => $request->user()?->id,
                'conversation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'error' => 'Conversation non trouvée',
                ], 404);
            }

            return response()->json([
                'error' => 'Impossible de supprimer la conversation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'title' => 'required|string|max:255',
            ]);

            $conversation = $user->aiConversations()
                ->where('id', $id)
                ->firstOrFail();

            Gate::authorize('update', $conversation);

            $conversation->update([
                'title' => $validated['title'],
            ]);

            return response()->json([
                'message' => 'Titre mis à jour avec succès',
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Conversation update error', [
                'user_id' => $request->user()?->id,
                'conversation_id' => $id,
                'message' => $e->getMessage(),
            ]);

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'error' => 'Conversation non trouvée',
                ], 404);
            }

            return response()->json([
                'error' => 'Impossible de mettre à jour la conversation',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
