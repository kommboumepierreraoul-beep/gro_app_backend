<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphMany, HasOne};

class Comment extends Model
{
    use SoftDeletes;

    // -------------------------------------------------------------------------
    // FILLABLE
    // -------------------------------------------------------------------------

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
    ];

    // -------------------------------------------------------------------------
    // CASTS
    // -------------------------------------------------------------------------

    protected $casts = [
        'likes_count' => 'integer',
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

    // -------------------------------------------------------------------------
    // MODERATION - RELATIONS
    // -------------------------------------------------------------------------

    public function moderation(): HasOne
    {
        return $this->hasOne(ModerationComment::class);
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

    public function isTopLevel(): bool
    {
        return is_null($this->parent_id);
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
        return hash('sha256', $this->content ?? '');
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
        return $this->scopeModerationStatus($query, 'pending');
    }

    public function scopeApproved($query)
    {
        return $this->scopeModerationStatus($query, 'approved');
    }

    public function scopeReview($query)
    {
        return $this->scopeModerationStatus($query, 'review');
    }

    public function scopeRejected($query)
    {
        return $this->scopeModerationStatus($query, 'rejected');
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

    // -------------------------------------------------------------------------
    // SCOPES - REVIEW QUEUE
    // -------------------------------------------------------------------------

    public function scopeReviewQueue($query)
    {
        return $query->whereHas('moderation', function ($q) {
            $q->where('status', 'review');
        })->with('moderation', 'post', 'author')
            ->orderByRaw(
                'GREATEST(
                (SELECT toxicity_score FROM moderation_comments WHERE comment_id = comments.id),
                (SELECT spam_score FROM moderation_comments WHERE comment_id = comments.id),
                (SELECT hate_score FROM moderation_comments WHERE comment_id = comments.id),
                (SELECT violence_score FROM moderation_comments WHERE comment_id = comments.id)
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

    // -------------------------------------------------------------------------
    // SCOPES - HIERARCHIE
    // -------------------------------------------------------------------------

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    // -------------------------------------------------------------------------
    // SCOPES - DATE
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    public function getRepliesCount(): int
    {
        return $this->replies()->count();
    }

    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function getThread(): array
    {
        $thread = [];
        $current = $this;

        while ($current) {
            $thread[] = $current;
            $current = $current->parent;
        }

        return array_reverse($thread);
    }
}
