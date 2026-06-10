<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReminder extends Model
{
    const TYPE_48H           = 'approaching_48h';
    const TYPE_24H           = 'approaching_24h';
    const TYPE_2H            = 'approaching_2h';
    const TYPE_STARTED       = 'started';
    const TYPE_REVIEW_PROMPT = 'review_prompt';

    protected $fillable = [
        'mission_id',
        'user_id',
        'remind_at',
        'type',
        'sent',
        'sent_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'sent_at'   => 'datetime',
        'sent'      => 'boolean',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
