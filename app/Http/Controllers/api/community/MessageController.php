<?php
namespace App\Http\Controllers\Api\Community;
use App\Http\Controllers\Controller;
use App\Models\{Conversation, Message, User};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Storage, Validator};

class MessageController extends Controller
{
    // Liste des conversations
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::with(['participants.profile', 'lastMessage.sender'])
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->latest('updated_at')
            ->paginate(20);

        $conversations->getCollection()->transform(fn($conv) => [
            'id' => $conv->id,
            'is_group' => $conv->is_group,
            'name' => $conv->name,
            'participants' => $conv->participants->map(fn($p) => [
                'id' => $p->id,
                'firstname' => $p->firstname,
                'lastname' => $p->lastname,
                'avatar' => $p->avatar,
            ]),
            'last_message' => $conv->lastMessage ? [
                'content' => $conv->lastMessage->content,
                'sender' => $conv->lastMessage->sender->firstname,
                'created_at' => $conv->lastMessage->created_at,
            ] : null,
            'unread_count' => $conv->unreadCountFor($user->id),
            'updated_at' => $conv->updated_at,
        ]);

        return response()->json(['success' => true, 'data' => $conversations]);
    }

    // Créer ou retrouver une conversation privée
    public function createOrFind(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $targetId = $request->user_id;

        if ($user->id === $targetId) {
            return response()->json(['success' => false, 'message' => 'Action invalide.'], 422);
        }

        // Chercher une conversation existante entre les deux
        $conversation = Conversation::where('is_group', false)
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $targetId))
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->participants()->attach([$user->id, $targetId]);
        }

        $conversation->load(['participants.profile', 'lastMessage.sender']);

        return response()->json(['success' => true, 'data' => $conversation]);
    }

    // Messages d'une conversation
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Vérifier que l'utilisateur fait partie de la conversation
        if (!$conversation->participants->contains($user->id)) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $messages = Message::with('sender.profile')
            ->where('conversation_id', $conversationId)
            ->latest()
            ->paginate(30);

        // Marquer comme lu
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $messages->getCollection()->transform(fn($msg) => [
            'id' => $msg->id,
            'content' => $msg->content,
            'media_url' => $msg->media_url,
            'status' => $msg->status,
            'is_mine' => $msg->sender_id === $user->id,
            'sender' => [
                'id' => $msg->sender->id,
                'firstname' => $msg->sender->firstname,
                'avatar' => $msg->sender->avatar,
            ],
            'created_at' => $msg->created_at,
        ]);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    // Envoyer un message
    public function send(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->participants->contains($user->id)) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required_without:media|nullable|string|max:5000',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $mediaUrl = null;
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('community/messages', 'public');
            $mediaUrl = Storage::url($path);
        }

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'content' => $request->content ?? '',
            'media_url' => $mediaUrl,
            'status' => 'sent',
        ]);

        $conversation->touch(); // updated_at pour trier les conversations
        $message->load('sender.profile');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'content' => $message->content,
                'media_url' => $message->media_url,
                'status' => $message->status,
                'is_mine' => true,
                'sender' => ['id' => $user->id, 'firstname' => $user->firstname, 'avatar' => $user->avatar],
                'created_at' => $message->created_at,
            ],
        ], 201);
    }

    // Supprimer un message
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        $message = Message::findOrFail($messageId);

        if ($message->sender_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
        }

        $message->delete();

        return response()->json(['success' => true, 'message' => 'Message supprimé.']);
    }
}
