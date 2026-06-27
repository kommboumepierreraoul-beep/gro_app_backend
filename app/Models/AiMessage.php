<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens',
        'position',
        'in_context_window',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'tokens'            => 'integer',
        'position'          => 'integer',
        'in_context_window' => 'boolean',
        'meta'              => 'array',
        'created_at'        => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
