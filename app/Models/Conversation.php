<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany, HasOne};

class Conversation extends Model
{
    protected $fillable = ['name', 'is_group'];

    protected $casts = ['is_group' => 'boolean'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->participants()->where('user_id', $userId)->first()?->pivot;

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->when($pivot?->last_read_at, fn($q, $date) => $q->where('created_at', '>', $date))
            ->count();
    }
}