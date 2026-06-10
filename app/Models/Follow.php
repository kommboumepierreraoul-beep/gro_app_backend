<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $follower_id
 * @property int $following_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $follower
 * @property-read \App\Models\User $following
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow whereFollowerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow whereFollowingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Follow whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Follow extends Model
{
    protected $fillable = ['follower_id', 'following_id'];

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}