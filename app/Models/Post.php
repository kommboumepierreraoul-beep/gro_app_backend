<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany, HasOne};

class Post extends Model
{
    use HasFactory, SoftDeletes;

    // -------------------------------------------------------------------------
    // FILLABLE
    // -------------------------------------------------------------------------

    protected $fillable = [
        'user_id',
        'content',
        'title',
        'cover_image',
        'type',
        'media_urls',
        'pdf_files',
        'shared_post_id',
        'expires_at',
        'likes_count',
        'comments_count',
        'shares_count',
    ];

    // -------------------------------------------------------------------------
    // CASTS
    // -------------------------------------------------------------------------

    protected $casts = [
        'media_urls' => 'array',
        'pdf_files' => 'array',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'shares_count' => 'integer',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // APPENDS
    // -------------------------------------------------------------------------

    protected $appends = [
        'moderation_status',
        'max_score',
    ];

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

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

    public function moderation(): HasOne
    {
        return $this->hasOne(ModerationPost::class);
    }

    // -------------------------------------------------------------------------
    // ACCESSORS - MODERATION
    // -------------------------------------------------------------------------

    public function getModerationStatusAttribute(): string
    {
        return $this->moderation?->status ?? 'pending';
    }

    public function getToxicityScoreAttribute(): ?float
    {
        return $this->moderation?->toxicity_score;
    }

    public function getSpamScoreAttribute(): ?float
    {
        return $this->moderation?->spam_score;
    }

    public function getHateScoreAttribute(): ?float
    {
        return $this->moderation?->hate_score;
    }

    public function getViolenceScoreAttribute(): ?float
    {
        return $this->moderation?->violence_score;
    }

    public function getModerationReasonAttribute(): ?string
    {
        return $this->moderation?->reason;
    }

    public function getMaxScoreAttribute(): float
    {
        return max(
            $this->toxicity_score ?? 0,
            $this->spam_score ?? 0,
            $this->hate_score ?? 0,
            $this->violence_score ?? 0
        );
    }

    public function getModeratedAtAttribute(): ?string
    {
        return $this->moderation?->moderated_at;
    }

    // -------------------------------------------------------------------------
    // HELPERS - MODERATION
    // -------------------------------------------------------------------------

    public function isModerationPending(): bool
    {
        return $this->moderation_status === 'pending';
    }

    public function isModerationApproved(): bool
    {
        return $this->moderation_status === 'approved';
    }

    public function isModerationRejected(): bool
    {
        return $this->moderation_status === 'rejected';
    }

    public function isModerationReview(): bool
    {
        return $this->moderation_status === 'review';
    }

    public function isModerated(): bool
    {
        return !is_null($this->moderation?->moderated_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAnnouncement(): bool
    {
        return $this->type === 'announcement';
    }

    public function isShared(): bool
    {
        return !is_null($this->shared_post_id);
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }

    public function isVisible(): bool
    {
        if ($this->isModerationRejected()) {
            return false;
        }

        if ($this->isModerationPending()) {
            return false;
        }

        if ($this->isModerationReview()) {
            return false;
        }

        return true;
    }

    public function generateContentHash(): string
    {
        return hash('sha256', ($this->title ?? '') . '|' . ($this->content ?? ''));
    }

    // -------------------------------------------------------------------------
    // SCOPES - MODERATION
    // -------------------------------------------------------------------------

    public function scopeModerationStatus($query, $status)
    {
        return $query->whereHas('moderation', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    public function scopePending($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'pending');
        });
    }

    public function scopeApproved($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'approved');
        });
    }

    public function scopeReview($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'review');
        });
    }

    public function scopeRejected($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'rejected');
        });
    }

    public function scopeNotModerated($query)
    {
        return $query->whereDoesntHave('moderation');
    }

    public function scopeVisible($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'approved');
        });
    }

    public function scopeReviewQueue($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'review');
        })->with('moderation', 'author')
            ->orderByRaw(
                'GREATEST(
                (SELECT toxicity_score FROM moderation_posts WHERE post_id = posts.id),
                (SELECT spam_score FROM moderation_posts WHERE post_id = posts.id),
                (SELECT hate_score FROM moderation_posts WHERE post_id = posts.id),
                (SELECT violence_score FROM moderation_posts WHERE post_id = posts.id)
            ) DESC'
            );
    }

    public function scopeHighRisk($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('toxicity_score', '>=', 0.7)
                ->orWhere('spam_score', '>=', 0.7)
                ->orWhere('hate_score', '>=', 0.7)
                ->orWhere('violence_score', '>=', 0.7);
        });
    }

    public function scopeFeed($query, int $userId)
    {
        $followingIds = Follow::where('follower_id', $userId)->pluck('following_id');

        return $query
            ->visible()
            ->latest()
            ->orderByRaw("CASE WHEN user_id = ? OR user_id IN (" .
                implode(',', $followingIds->push($userId)->toArray()) .
                ") THEN 0 ELSE 1 END", [$userId]);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeAnnouncements($query)
    {
        return $query->where('type', 'announcement');
    }

    public function scopeText($query)
    {
        return $query->where('type', 'text');
    }

    public function scopeWithImages($query)
    {
        return $query->where('type', 'image');
    }

    public function scopeWithVideos($query)
    {
        return $query->where('type', 'video');
    }

    public function scopeShared($query)
    {
        return $query->whereNotNull('shared_post_id');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }
}
