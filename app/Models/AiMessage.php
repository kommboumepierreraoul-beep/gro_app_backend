<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message individuel dans une conversation IA.
 *
 * @property int    $id
 * @property int    $conversation_id
 * @property string $role            'user' | 'assistant' | 'system'
 * @property string $content
 * @property int    $tokens_used     Tokens consommés (renseigné sur les messages assistant)
 */
class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens_used',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
