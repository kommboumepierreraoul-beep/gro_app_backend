<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation IA d'un utilisateur.
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $session_id   UUID unique par session
 * @property string|null $title        Titre auto-généré depuis le 1er message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'title',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at');
    }

    // ─── Scopes ──────────────────────────────────────────────

    /**
     * Conversations d'un utilisateur, plus récentes en premier.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->orderByDesc('updated_at');
    }
}
