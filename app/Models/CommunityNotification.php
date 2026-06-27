<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * @property int $id
 * @property int $user_id
 * @property int $actor_id
 * @property string $type
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property string $message
 * @property bool $is_read
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $actor
 * @property-read Model|\Eloquent|null $notifiable
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereIsRead($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereNotifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereNotifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommunityNotification whereUserId($value)
 * @mixin \Eloquent
 */
class CommunityNotification extends Model
{
    protected $table = 'community_notifications';

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'message',
        'is_read',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}