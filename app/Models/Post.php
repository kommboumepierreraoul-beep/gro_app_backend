<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'content',
        'type',
        'media_urls',
        'pdf_files',
        'shared_post_id',
        'likes_count',
        'comments_count',
        'shares_count',
    ];

    protected $casts = [
        'media_urls' => 'array',
        'pdf_files' => 'array',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->withDefault(['firstname' => 'Utilisateur supprimé']);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'shared_post_id')->withTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeFeed($query, int $userId)
    {
        // Posts de l'utilisateur + ceux des personnes qu'il suit
        $followingIds = Follow::where('follower_id', $userId)->pluck('following_id');
        $ids = $followingIds->push($userId);

        return $query->whereIn('user_id', $ids)->latest();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}
