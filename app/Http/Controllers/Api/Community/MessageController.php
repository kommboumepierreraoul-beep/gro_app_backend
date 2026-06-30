<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    // Liste des conversations
    public function conversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $conversations = Conversation::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
                ->latest('updated_at')
                ->get();

            $result = [];
            foreach ($conversations as $conv) {
                // Récupérer les participants avec leur avatar depuis user_profiles
                $participants = DB::table('conversation_user')
                    ->join('users', 'conversation_user.user_id', '=', 'users.id')
                    ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                    ->where('conversation_user.conversation_id', $conv->id)
                    ->select('users.id', 'users.firstname', 'users.lastname', 'user_profiles.avatar')
                    ->get();

                $participantsArray = [];
                foreach ($participants as $p) {
                    $participantsArray[] = [
                        'id' => $p->id,
                        'firstname' => $p->firstname,
                        'lastname' => $p->lastname,
                        'avatar' => $p->avatar,
                    ];
                }

                $lastMessage = Message::where('conversation_id', $conv->id)
                    ->with('sender')
                    ->latest()
                    ->first();

                // Calcul des messages non lus
                $lastReadAt = DB::table('conversation_user')
                    ->where('conversation_id', $conv->id)
                    ->where('user_id', $user->id)
                    ->value('last_read_at');

                $unreadCount = Message::where('conversation_id', $conv->id)
                    ->where('sender_id', '!=', $user->id)
                    ->when($lastReadAt, function ($q) use ($lastReadAt) {
                        return $q->where('created_at', '>', $lastReadAt);
                    })
                    ->count();

                $result[] = [
                    'id' => $conv->id,
                    'is_group' => $conv->is_group,
                    'name' => $conv->name,
                    'participants' => $participantsArray,
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'media_url' => $lastMessage->media_url,
                        'media_type' => $lastMessage->media_type,
                        'media_size' => $lastMessage->media_size,
                        'file_name' => $lastMessage->file_name,
                        'sender' => $lastMessage->sender->firstname ?? 'Utilisateur',
                        'sender_id' => $lastMessage->sender_id,
                        'created_at' => $lastMessage->created_at,
                    ] : null,
                    'unread_count' => $unreadCount,
                    'updated_at' => $conv->updated_at,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $result,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => count($result)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Conversations error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Créer ou trouver une conversation
    public function createOrFind(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Création de groupe
            if ($request->has('participants')) {
                $validator = Validator::make($request->all(), [
                    'participants' => 'required|array|min:1',
                    'participants.*' => 'required|distinct|exists:users,id',
                    'name' => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
                }

                $participantIds = array_map('intval', $request->participants);
                if (!in_array($user->id, $participantIds)) {
                    $participantIds[] = $user->id;
                }

                DB::beginTransaction();
                $conversation = Conversation::create([
                    'is_group' => true,
                    'name' => $request->name ?? null,
                ]);

                foreach ($participantIds as $pid) {
                    DB::table('conversation_user')->insert([
                        'conversation_id' => $conversation->id,
                        'user_id' => $pid,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::commit();

                // Récupérer les participants avec leur avatar
                $participants = DB::table('conversation_user')
                    ->join('users', 'conversation_user.user_id', '=', 'users.id')
                    ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                    ->where('conversation_user.conversation_id', $conversation->id)
                    ->select('users.id', 'users.firstname', 'users.lastname', 'user_profiles.avatar')
                    ->get();

                $conversation->participants = $participants;

                return response()->json(['success' => true, 'data' => $conversation]);
            }

            // Conversation privée
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $targetId = (int) $request->user_id;

            if ($user->id === $targetId) {
                return response()->json(['success' => false, 'message' => 'Action invalide.'], 422);
            }

            // Chercher une conversation existante
            $conversationId = DB::table('conversation_user as cu1')
                ->where('cu1.user_id', $user->id)
                ->join('conversation_user as cu2', 'cu1.conversation_id', '=', 'cu2.conversation_id')
                ->where('cu2.user_id', $targetId)
                ->join('conversations', 'cu1.conversation_id', '=', 'conversations.id')
                ->where('conversations.is_group', false)
                ->select('cu1.conversation_id')
                ->first();

            if ($conversationId) {
                $conversation = Conversation::find($conversationId->conversation_id);
            } else {
                DB::beginTransaction();
                $conversation = Conversation::create(['is_group' => false]);
                DB::table('conversation_user')->insert([
                    ['conversation_id' => $conversation->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
                    ['conversation_id' => $conversation->id, 'user_id' => $targetId, 'created_at' => now(), 'updated_at' => now()],
                ]);
                DB::commit();
            }

            // Récupérer les participants avec leur avatar
            $participants = DB::table('conversation_user')
                ->join('users', 'conversation_user.user_id', '=', 'users.id')
                ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
                ->where('conversation_user.conversation_id', $conversation->id)
                ->select('users.id', 'users.firstname', 'users.lastname', 'user_profiles.avatar')
                ->get();

            $conversation->participants = $participants;

            return response()->json(['success' => true, 'data' => $conversation]);
        } catch (\Exception $e) {
            Log::error('CreateOrFind error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Messages d'une conversation
    public function messages(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier l'accès
            $hasAccess = DB::table('conversation_user')
                ->where('conversation_id', $conversationId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$hasAccess) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $messages = Message::where('conversation_id', $conversationId)
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->paginate(30);

            $messages->getCollection()->transform(function ($msg) use ($user) {
                // Récupérer l'avatar du sender depuis user_profiles
                $senderAvatar = DB::table('user_profiles')
                    ->where('user_id', $msg->sender_id)
                    ->value('avatar');

                return [
                    'id' => $msg->id,
                    'content' => $msg->content,
                    'media_url' => $msg->media_url,
                    'media_type' => $msg->media_type,
                    'media_size' => $msg->media_size,
                    'file_name' => $msg->file_name,
                    'status' => $msg->status,
                    'is_mine' => $msg->sender_id === $user->id,
                    'sender_id' => $msg->sender_id,
                    'sender' => [
                        'id' => $msg->sender->id,
                        'firstname' => $msg->sender->firstname,
                        'lastname' => $msg->sender->lastname ?? '',
                        'avatar' => $senderAvatar,
                    ],
                    'created_at' => $msg->created_at,
                ];
            });

            return response()->json(['success' => true, 'data' => $messages]);
        } catch (\Exception $e) {
            Log::error('Messages error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Envoyer un message - Supporte TOUS les types de fichiers
    public function send(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier l'accès
            $hasAccess = DB::table('conversation_user')
                ->where('conversation_id', $conversationId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$hasAccess) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }

            $content = $request->input('content', '');
            $mediaUrl = null;

            // Upload du fichier
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('messages', $filename, 'public');
                $mediaUrl = asset('storage/' . $path);

                Log::info('File uploaded', ['path' => $path, 'url' => $mediaUrl]);
            }

            // Vérifier qu'il y a du contenu
            if (empty($content) && !$mediaUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le message ne peut pas être vide'
                ], 422);
            }

            // Créer le message (uniquement les colonnes qui existent)
            $messageData = [
                'conversation_id' => $conversationId,
                'sender_id' => $user->id,
                'content' => $content,
                'media_url' => $mediaUrl,
                'status' => 'sent',
            ];

            // Ajouter les colonnes seulement si elles existent
            $columns = DB::getSchemaBuilder()->getColumnListing('messages');

            if (in_array('media_type', $columns)) {
                $messageData['media_type'] = $mediaUrl ? 'file' : null;
            }
            if (in_array('media_size', $columns)) {
                $messageData['media_size'] = null;
            }
            if (in_array('file_name', $columns)) {
                $messageData['file_name'] = null;
            }

            $message = Message::create($messageData);

            // Mettre à jour la conversation
            DB::table('conversations')
                ->where('id', $conversationId)
                ->update(['updated_at' => now()]);

            // Récupérer l'avatar
            $userAvatar = DB::table('user_profiles')
                ->where('user_id', $user->id)
                ->value('avatar');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'media_url' => $message->media_url,
                    'status' => $message->status,
                    'is_mine' => true,
                    'sender_id' => $user->id,
                    'sender' => [
                        'id' => $user->id,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname ?? '',
                        'avatar' => $userAvatar,
                    ],
                    'created_at' => $message->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Send error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Récupérer le statut d'un message spécifique
    public function getMessageStatus(Request $request, int $messageId): JsonResponse
    {
        try {
            $user = $request->user();
            $message = Message::findOrFail($messageId);

            // Vérifier que l'utilisateur a accès à cette conversation
            $hasAccess = DB::table('conversation_user')
                ->where('conversation_id', $message->conversation_id)
                ->where('user_id', $user->id)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.'
                ], 403);
            }

            // Déterminer le statut basé sur la colonne 'status'
            $is_read = $message->status === 'read';
            $is_delivered = in_array($message->status, ['delivered', 'read']);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_read' => $is_read,
                    'is_delivered' => $is_delivered,
                    'status' => $message->status,
                    'read_at' => $message->status === 'read' ? $message->updated_at : null,
                    'delivered_at' => $message->status === 'delivered' || $message->status === 'read'
                        ? $message->updated_at
                        : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('GetMessageStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Marquer comme lu
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        try {
            $user = $request->user();

            DB::table('conversation_user')
                ->where('conversation_id', $conversationId)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);

            return response()->json(['success' => true, 'message' => 'Marqué comme lu']);
        } catch (\Exception $e) {
            Log::error('MarkAsRead error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Supprimer un message
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        try {
            $message = Message::findOrFail($messageId);

            if ($message->sender_id !== $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Action non autorisée.'], 403);
            }

            // Supprimer le fichier média s'il existe
            if ($message->media_url) {
                try {
                    $path = str_replace('/storage/', '', parse_url($message->media_url, PHP_URL_PATH));
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                        Log::info('File deleted:', ['path' => $path]);
                    }
                } catch (\Exception $e) {
                    Log::error('Delete file error: ' . $e->getMessage());
                }
            }

            $message->delete();

            return response()->json(['success' => true, 'message' => 'Message supprimé.']);
        } catch (\Exception $e) {
            Log::error('DeleteMessage error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
