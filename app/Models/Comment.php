<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany};

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $content
 * @property-read int|null $likes_count
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Like> $likes
 * @property-read Comment|null $parent
 * @property-read \App\Models\Post|null $post
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Comment> $replies
 * @property-read int|null $replies_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereLikesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment withoutTrashed()
 * @mixin \Eloquent
 */
class Comment extends Model
{
    use SoftDeletes;
    protected $fillable = ['post_id', 'user_id', 'parent_id', 'content', 'likes_count'];
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
                    ->withDefault(['firstname' => 'Utilisateur supprimé']);
    }
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}