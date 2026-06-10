<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Str;

/**
 * Gère les conversations multi-tours avec DeepSeek.
 *
 * Responsabilités :
 *  - Création / récupération de sessions de conversation
 *  - Persistance des messages (user + assistant) en base
 *  - Construction du contexte (historique glissant des N derniers messages)
 *  - Appel à DeepSeekService (réponse complète ou streaming)
 */
class ConversationService
{
    /**
     * Nombre de messages maximum envoyés comme contexte à l'API.
     * Limite les coûts et évite de dépasser la fenêtre de contexte.
     */
    private const MAX_CONTEXT_MESSAGES = 20;

    /**
     * Prompt système injecté en tête de chaque requête.
     */
    private string $systemPrompt = <<<'PROMPT'
Tu es l'assistant IA officiel de notre communauté en ligne.
Ton rôle est d'aider les membres à :
- Trouver des informations sur la communauté
- Rédiger et améliorer leurs posts
- Naviguer et découvrir du contenu pertinent
- Répondre à leurs questions générales

Règles :
- Sois amical, concis et utile
- Ne partage aucune information personnelle d'autres membres
- Redirige les demandes de modération vers les modérateurs humains
- Réponds toujours dans la langue de l'utilisateur
PROMPT;

    public function __construct(
        public readonly DeepSeekService $deepSeek
    ) {}

    // ──────────────────────────────────────────────────────────
    // GESTION DES CONVERSATIONS
    // ──────────────────────────────────────────────────────────

    /**
     * Récupère une conversation existante ou en crée une nouvelle.
     */
    public function getOrCreateConversation(int $userId, string $sessionId): AiConversation
    {
        return AiConversation::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id'    => $userId,
                'session_id' => $sessionId,
            ]
        );
    }

    /**
     * Démarre une nouvelle conversation et retourne son session_id.
     */
    public function startNewConversation(int $userId): AiConversation
    {
        return AiConversation::create([
            'user_id'    => $userId,
            'session_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * Retourne les conversations récentes d'un utilisateur (paginated).
     */
    public function getUserConversations(int $userId, int $perPage = 10)
    {
        return AiConversation::where('user_id', $userId)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }

    // ──────────────────────────────────────────────────────────
    // ENVOI DE MESSAGE (réponse complète)
    // ──────────────────────────────────────────────────────────

    /**
     * Envoie un message utilisateur et retourne la réponse de l'IA.
     *
     * @return array{success: bool, content: string, session_id: string, error?: string}
     */
    public function sendMessage(int $userId, string $userMessage, string $sessionId): array
    {
        $conversation = $this->getOrCreateConversation($userId, $sessionId);

        // 1. Persiste le message utilisateur
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        // 2. Construit le contexte à envoyer à l'API
        $messages = $this->buildContext($conversation);

        // 3. Appel API
        $result = $this->deepSeek->chat($messages);

        if (! $result['success']) {
            return [
                'success'    => false,
                'error'      => $result['error'],
                'session_id' => $sessionId,
                'content'    => '',
            ];
        }

        // 4. Persiste la réponse de l'assistant
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $result['content'],
            'tokens_used'     => $result['usage']['total_tokens'] ?? 0,
        ]);

        // 5. Met à jour le titre de la conversation si c'est la première réponse
        if ($conversation->messages()->count() === 2 && ! $conversation->title) {
            $conversation->update([
                'title' => $this->generateConversationTitle($userMessage),
            ]);
        }

        // 6. Touch pour updated_at (tri dans la liste des conversations)
        $conversation->touch();

        return [
            'success'    => true,
            'content'    => $result['content'],
            'session_id' => $sessionId,
            'usage'      => $result['usage'],
        ];
    }

    // ──────────────────────────────────────────────────────────
    // ENVOI DE MESSAGE (streaming SSE)
    // ──────────────────────────────────────────────────────────

    /**
     * Envoie un message en mode streaming et sauvegarde la réponse complète.
     *
     * @param  callable  $onChunk   Appelé pour chaque token reçu
     * @param  callable  $onDone    Appelé avec la réponse complète en paramètre
     */
    public function sendMessageStream(
        int $userId,
        string $userMessage,
        string $sessionId,
        callable $onChunk,
        callable $onDone
    ): void {
        $conversation = $this->getOrCreateConversation($userId, $sessionId);

        // Persiste le message utilisateur
        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $userMessage,
        ]);

        $messages      = $this->buildContext($conversation);
        $fullResponse  = '';

        // ✅ CORRECTION : Passe un tableau options vide comme 4ème paramètre
        $this->deepSeek->chatStream(
            messages: $messages,
            onChunk: function (string $chunk) use (&$fullResponse, $onChunk) {
                $fullResponse .= $chunk;
                $onChunk($chunk);
            },
            options: []  // <-- Ajoute ce paramètre (même vide)
        );

        // Persiste la réponse complète
        if ($fullResponse !== '') {
            AiMessage::create([
                'conversation_id' => $conversation->id,
                'role'            => 'assistant',
                'content'         => $fullResponse,
            ]);

            $conversation->touch();
        }

        $onDone($fullResponse);
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS PRIVÉS
    // ──────────────────────────────────────────────────────────

    /**
     * Construit le tableau de messages à envoyer à l'API.
     * Inclut le system prompt + les N derniers messages de la conversation.
     */
    private function buildContext(AiConversation $conversation): array
    {
        $history = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->map(fn(AiMessage $m) => [
                'role'    => $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();

        return array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt]],
            $history
        );
    }

    /**
     * Génère un titre court pour la conversation à partir du premier message.
     */
    private function generateConversationTitle(string $firstMessage): string
    {
        return mb_substr($firstMessage, 0, 60) . (mb_strlen($firstMessage) > 60 ? '…' : '');
    }
}
