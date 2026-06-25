<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ModerationPost extends Model
{
    protected $table = 'moderation_posts';

    protected $fillable = [
        'post_id',
        'status',
        'toxicity_score',
        'spam_score',
        'hate_score',
        'violence_score',
        'result_raw',
        'reason',
        'content_hash',
        'moderated_at',
    ];

    protected $casts = [
        'result_raw' => 'array',
        'moderated_at' => 'datetime',
        'toxicity_score' => 'float',
        'spam_score' => 'float',
        'hate_score' => 'float',
        'violence_score' => 'float',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(ModerationAuditLog::class, 'moderatable');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getMaxScoreAttribute(): float
    {
        return max(
            $this->toxicity_score ?? 0,
            $this->spam_score ?? 0,
            $this->hate_score ?? 0,
            $this->violence_score ?? 0
        );
    }

    public function getRiskLevelAttribute(): string
    {
        $max = $this->getMaxScoreAttribute();

        if ($max >= 0.7) {
            return 'high';
        } elseif ($max >= 0.4) {
            return 'medium';
        }

        return 'low';
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isReview(): bool
    {
        return $this->status === 'review';
    }

    public function isModerated(): bool
    {
        return !is_null($this->moderated_at);
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function updateStatus(
        string $newStatus,
        string $actorType,
        ?int $actorId = null,
        array $payload = []
    ): self {
        $oldStatus = $this->status;

        $this->update([
            'status' => $newStatus,
            'moderated_at' => now(),
        ]);

        $this->auditLogs()->create([
            'action' => $newStatus,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'payload' => array_merge($payload, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'post_id' => $this->post_id,
                'user_id' => $this->post?->user_id,
            ]),
        ]);

        return $this;
    }

    public function approve(string $actorType = 'moderator', ?int $actorId = null, array $payload = []): self
    {
        return $this->updateStatus('approved', $actorType, $actorId, $payload);
    }

    public function reject(string $actorType = 'moderator', ?int $actorId = null, array $payload = []): self
    {
        return $this->updateStatus('rejected', $actorType, $actorId, $payload);
    }

    public function setReview(string $actorType = 'moderator', ?int $actorId = null, array $payload = []): self
    {
        return $this->updateStatus('review', $actorType, $actorId, $payload);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeReview($query)
    {
        return $query->where('status', 'review');
    }

    public function scopeHighRisk($query)
    {
        return $query->where('toxicity_score', '>=', 0.7)
            ->orWhere('spam_score', '>=', 0.7)
            ->orWhere('hate_score', '>=', 0.7)
            ->orWhere('violence_score', '>=', 0.7);
    }

    public function scopeMediumRisk($query)
    {
        return $query->where(function ($q) {
            $q->whereBetween('toxicity_score', [0.4, 0.69])
                ->orWhereBetween('spam_score', [0.4, 0.69])
                ->orWhereBetween('hate_score', [0.4, 0.69])
                ->orWhereBetween('violence_score', [0.4, 0.69]);
        });
    }

    public function scopeLowRisk($query)
    {
        return $query->where('toxicity_score', '<', 0.4)
            ->where('spam_score', '<', 0.4)
            ->where('hate_score', '<', 0.4)
            ->where('violence_score', '<', 0.4);
    }

    public function scopeReviewQueue($query)
    {
        return $query->where('status', 'review')
            ->orderByRaw('GREATEST(toxicity_score, spam_score, hate_score, violence_score) DESC');
    }

    // ── Statistiques (méthodes statiques) ───────────────────────────────────

    public static function getStats(): array
    {
        return [
            'total' => self::count(),
            'pending' => self::where('status', 'pending')->count(),
            'approved' => self::where('status', 'approved')->count(),
            'review' => self::where('status', 'review')->count(),
            'rejected' => self::where('status', 'rejected')->count(),
            'avg_toxicity' => self::avg('toxicity_score'),
            'avg_spam' => self::avg('spam_score'),
            'avg_hate' => self::avg('hate_score'),
            'avg_violence' => self::avg('violence_score'),
            // ✅ Correction: Utiliser where() directement au lieu de highRisk()
            'high_risk' => self::where('toxicity_score', '>=', 0.7)
                ->orWhere('spam_score', '>=', 0.7)
                ->orWhere('hate_score', '>=', 0.7)
                ->orWhere('violence_score', '>=', 0.7)
                ->count(),
            'medium_risk' => self::where(function ($q) {
                $q->whereBetween('toxicity_score', [0.4, 0.69])
                    ->orWhereBetween('spam_score', [0.4, 0.69])
                    ->orWhereBetween('hate_score', [0.4, 0.69])
                    ->orWhereBetween('violence_score', [0.4, 0.69]);
            })->count(),
            'low_risk' => self::where('toxicity_score', '<', 0.4)
                ->where('spam_score', '<', 0.4)
                ->where('hate_score', '<', 0.4)
                ->where('violence_score', '<', 0.4)
                ->count(),
        ];
    }
}
