<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'content',
        'media_url',
        'status',
        'media_type',
        'media_size',
        'file_name'
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')
            ->withDefault(['firstname' => 'Utilisateur supprimé']);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}