<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};

/**
 * @property-read \App\Models\User|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Like> $likes
 * @property-read int|null $likes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Announcement withoutTrashed()
 * @mixin \Eloquent
 */
class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'category',
        'cover_image',
        'expires_at',
        'likes_count',
    ];

    protected $table = 'posts'; // ← pointe vers posts

    protected static function booted(): void
    {
        // Filtre automatique sur type = announcement
        static::addGlobalScope(
            'announcement',
            fn($q) =>
            $q->where('type', 'announcement')
        );
    }

    protected $casts = ['expires_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
