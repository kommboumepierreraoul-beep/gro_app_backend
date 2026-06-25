<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationAuditLog extends Model
{
    protected $table = 'moderation_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'moderatable_type',
        'moderatable_id',
        'action',
        'actor_type',
        'actor_id',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function moderatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getActorLabelAttribute(): string
    {
        return match ($this->actor_type) {
            'ai' => '🤖 Intelligence Artificielle',
            'moderator' => '👤 Modérateur',
            'system' => '⚙️ Système',
            default => '❓ Inconnu',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'pending' => '⏳ En attente',
            'approved' => '✅ Approuvé',
            'review' => '🔍 En révision',
            'rejected' => '❌ Rejeté',
            default => '❓ Inconnu',
        };
    }

    public function getContentTypeLabelAttribute(): string
    {
        return match ($this->moderatable_type) {
            ModerationPost::class => '📝 Publication',
            ModerationComment::class => '💬 Commentaire',
            ModerationMessage::class => '✉️ Message',
            default => '❓ Inconnu',
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isAiDecision(): bool
    {
        return $this->actor_type === 'ai';
    }

    public function isModeratorDecision(): bool
    {
        return $this->actor_type === 'moderator';
    }

    public function isSystemDecision(): bool
    {
        return $this->actor_type === 'system';
    }

    public function isApproval(): bool
    {
        return $this->action === 'approved';
    }

    public function isRejection(): bool
    {
        return $this->action === 'rejected';
    }

    public function isReview(): bool
    {
        return $this->action === 'review';
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAiDecisions($query)
    {
        return $query->where('actor_type', 'ai');
    }

    public function scopeModeratorDecisions($query)
    {
        return $query->where('actor_type', 'moderator');
    }

    public function scopeSystemDecisions($query)
    {
        return $query->where('actor_type', 'system');
    }

    public function scopeForContent($query, string $type, int $id)
    {
        return $query->where('moderatable_type', $type)
            ->where('moderatable_id', $id);
    }

    public function scopeForPost($query, int $postId)
    {
        return $this->scopeForContent($query, ModerationPost::class, $postId);
    }

    public function scopeForComment($query, int $commentId)
    {
        return $this->scopeForContent($query, ModerationComment::class, $commentId);
    }

    public function scopeForMessage($query, int $messageId)
    {
        return $this->scopeForContent($query, ModerationMessage::class, $messageId);
    }

    public function scopeApprovals($query)
    {
        return $query->where('action', 'approved');
    }

    public function scopeRejections($query)
    {
        return $query->where('action', 'rejected');
    }

    public function scopeReviews($query)
    {
        return $query->where('action', 'review');
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public static function getStats(): array
    {
        return [
            'total' => self::count(),
            'ai_decisions' => self::aiDecisions()->count(),
            'moderator_decisions' => self::moderatorDecisions()->count(),
            'system_decisions' => self::systemDecisions()->count(),
            'approvals' => self::approvals()->count(),
            'rejections' => self::rejections()->count(),
            'reviews' => self::reviews()->count(),
        ];
    }

    public static function getStatsForPeriod($startDate, $endDate): array
    {
        return self::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('action, actor_type, COUNT(*) as count')
            ->groupBy('action', 'actor_type')
            ->get()
            ->toArray();
    }

    public static function getDailyStats(int $days = 7): array
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, action, COUNT(*) as count')
            ->groupBy('date', 'action')
            ->orderBy('date')
            ->get()
            ->toArray();
    }
}
