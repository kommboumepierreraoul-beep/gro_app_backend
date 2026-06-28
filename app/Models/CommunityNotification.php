<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'message',
        'data',
        'is_read',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    public function isRead(): bool
    {
        return $this->is_read;
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeMission($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'like', 'mission_%')
                ->orWhere('type', 'review_request')
                ->orWhere('type', 'mission_report');
        });
    }

    public function scopeCommunity($query)
    {
        return $query->where(function ($q) {
            $q->where('type', 'like', 'like_%')
                ->orWhere('type', 'comment')
                ->orWhere('type', 'reply')
                ->orWhere('type', 'follow')
                ->orWhere('type', 'share')
                ->orWhere('type', 'mention')
                ->orWhere('type', 'announcement');
        });
    }
}
