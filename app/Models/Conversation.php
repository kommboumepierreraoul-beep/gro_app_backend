<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany, HasOne};

/**
 * @property int $id
 * @property string|null $name
 * @property bool $is_group
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Message|null $lastMessage
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $participants
 * @property-read int|null $participants_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereIsGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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