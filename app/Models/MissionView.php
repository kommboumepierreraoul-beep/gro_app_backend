<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionView extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['mission_id', 'user_id', 'ip_hash', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
