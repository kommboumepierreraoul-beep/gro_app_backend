<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};
 
/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property string $type
 * @property array<array-key, mixed>|null $media_urls
 * @property int|null $shared_post_id
 * @property-read int|null $likes_count
 * @property-read int|null $comments_count
 * @property int $shares_count
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $allComments
 * @property-read int|null $all_comments_count
 * @property-read \App\Models\User $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Like> $likes
 * @property-read Post|null $sharedPost
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post feed(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCommentsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereLikesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereMediaUrls($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSharedPostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereSharesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Post withoutTrashed()
 * @mixin \Eloquent
 */
class Post extends Model
{
    use HasFactory, SoftDeletes;
 
    protected $fillable = [
        'user_id', 'content', 'type', 'media_urls',
        'shared_post_id', 'likes_count', 'comments_count', 'shares_count',
    ];
 
    protected $casts = [
        'media_urls' => 'array',
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
 