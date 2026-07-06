<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Support\Str;

class ConversationService
{
    public const CONTEXT_WINDOW = 20; // nombre de messages max dans le contexte

    // ── Créer / récupérer une conversation ────────────────────────────────────

    public function findOrCreate(
        int $userId,
        ?string $conversationId = null,
        string $contextType = 'general',
        ?int $contextId = null
    ): AiConversation {
        if ($conversationId) {
            $conv = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->firstOrFail();
            return $conv;
        }

        return AiConversation::create([
            'user_id'      => $userId,
            'model'        => config('ai.model', 'deepseek-chat'),
            'context_type' => $contextType,
            'context_id'   => $contextId,
        ]);
    }

    // ── Ajouter un message ─────────────────────────────────────────────────────

    public function addMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        int $tokens = 0,
        array $meta = []
    ): AiMessage {
        $position = $conversation->message_count + 1;

        $message = AiMessage::create([
            'conversation_id'  => $conversation->id,
            'role'             => $role,
            'content'          => $content,
            'tokens'           => $tokens,
            'position'         => $position,
            'in_context_window' => true,
            'meta'             => $meta,
            'created_at'       => now(),
        ]);

        // Mise à jour des compteurs
        $conversation->increment('message_count');
        $conversation->increment('total_tokens', $tokens);

        // Gérer la fenêtre glissante
        $this->slideContextWindow($conversation);

        // Auto-titre sur le premier message utilisateur
        if ($position === 1 && $role === 'user') {
            $conversation->update([
                'title' => Str::limit($content, 60),
            ]);
        }

        return $message;
    }

    // ── Fenêtre glissante ──────────────────────────────────────────────────────

    /**
     * Quand le nombre de messages dépasse CONTEXT_WINDOW,
     * on marque les plus anciens comme hors fenêtre.
     * Ils restent en DB (historique) mais ne sont plus envoyés à l'API.
     */
    private function slideContextWindow(AiConversation $conversation): void
    {
        $count = AiMessage::where('conversation_id', $conversation->id)
            ->where('in_context_window', true)
            ->count();

        if ($count > self::CONTEXT_WINDOW) {
            $excess = $count - self::CONTEXT_WINDOW;

            AiMessage::where('conversation_id', $conversation->id)
                ->where('in_context_window', true)
                ->orderBy('position')
                ->limit($excess)
                ->update(['in_context_window' => false]);
        }
    }

    // ── Construire le payload API ──────────────────────────────────────────────

    /**
     * Retourne les messages formattés pour l'API DeepSeek/OpenAI,
     * avec le system prompt en tête.
     */
    public function buildApiMessages(
        AiConversation $conversation,
        ?string $systemPrompt = null,
        ?array $contextData = null
    ): array
    {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = [
                'role' => 'system',
                'content' => $this->withQualityPrompt($systemPrompt),
            ];
        }

        if (!empty($contextData)) {
            $messages[] = [
                'role' => 'system',
                'content' => "Contexte métier disponible. Utilise ces informations uniquement si elles sont utiles à la réponse, sans inventer de détails absents :\n\n" .
                    json_encode($contextData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        $history = $conversation->toApiMessages(self::CONTEXT_WINDOW);
        return array_merge($messages, $history);
    }

    private function withQualityPrompt(string $systemPrompt): string
    {
        $qualityPrompt = (string) config('ai.system_prompts.quality', '');

        if ($qualityPrompt === '' || str_contains($systemPrompt, $qualityPrompt)) {
            return $systemPrompt;
        }

        return trim($systemPrompt) . "\n\n" . trim($qualityPrompt);
    }

    // ── Historique paginé ──────────────────────────────────────────────────────

    public function getPaginatedHistory(int $userId, int $perPage = 20)
    {
        return AiConversation::where('user_id', $userId)
            ->withCount('messages')
            ->latest()
            ->paginate($perPage);
    }

    public function getConversation(int $userId, string $conversationId): ?AiConversation
    {
        return AiConversation::where('user_id', $userId)
            ->where('id', $conversationId)
            ->with('messages')
            ->first();
    }

    public function deleteConversation(int $userId, string $conversationId): bool
    {
        $conversation = AiConversation::where('user_id', $userId)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return false;
        }

        return (bool) $conversation->delete();
    }
}
