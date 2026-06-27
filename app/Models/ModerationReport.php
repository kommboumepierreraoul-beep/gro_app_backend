<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationReport extends Model
{
    protected $table = 'moderation_reports';

    protected $fillable = [
        'reporter_id',
        'content_type',
        'content_id',
        'reason',
        'description',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getContentTypeLabelAttribute(): string
    {
        return match ($this->content_type) {
            'post' => '📝 Publication',
            'comment' => '💬 Commentaire',
            'message' => '✉️ Message',
            'user' => '👤 Utilisateur',
            default => '❓ Inconnu',
        };
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'spam' => '📧 Spam',
            'harassment' => '⚠️ Harcèlement',
            'hate_speech' => '💢 Discours haineux',
            'violence' => '🔫 Violence',
            'inappropriate' => '🔞 Inapproprié',
            'misinformation' => '📢 Désinformation',
            'other' => '📌 Autre',
            default => '❓ Inconnu',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => '⏳ En attente',
            'reviewing' => '🔍 En révision',
            'resolved' => '✅ Résolu',
            'dismissed' => '❌ Rejeté',
            default => '❓ Inconnu',
        };
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReviewing(): bool
    {
        return $this->status === 'reviewing';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isDismissed(): bool
    {
        return $this->status === 'dismissed';
    }

    public function isResolvedByAi(): bool
    {
        return $this->resolver_id === null && !is_null($this->resolved_at);
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function resolve(int $resolverId): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $resolverId,
            'resolved_at' => now(),
        ]);
    }

    public function dismiss(int $resolverId): void
    {
        $this->update([
            'status' => 'dismissed',
            'resolved_by' => $resolverId,
            'resolved_at' => now(),
        ]);
    }

    public function startReview(): void
    {
        $this->update(['status' => 'reviewing']);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewing($query)
    {
        return $query->where('status', 'reviewing');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeDismissed($query)
    {
        return $query->where('status', 'dismissed');
    }

    public function scopeForPost($query, int $postId)
    {
        return $query->where('content_type', 'post')
            ->where('content_id', $postId);
    }

    public function scopeForComment($query, int $commentId)
    {
        return $query->where('content_type', 'comment')
            ->where('content_id', $commentId);
    }

    public function scopeForMessage($query, int $messageId)
    {
        return $query->where('content_type', 'message')
            ->where('content_id', $messageId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('content_type', 'user')
            ->where('content_id', $userId);
    }

    // ── Statistiques ─────────────────────────────────────────────────────────

    public static function getStats(): array
    {
        return [
            'total' => self::count(),
            'pending' => self::pending()->count(),
            'reviewing' => self::reviewing()->count(),
            'resolved' => self::resolved()->count(),
            'dismissed' => self::dismissed()->count(),
            'by_reason' => self::selectRaw('reason, COUNT(*) as count')
                ->groupBy('reason')
                ->get()
                ->toArray(),
            'by_type' => self::selectRaw('content_type, COUNT(*) as count')
                ->groupBy('content_type')
                ->get()
                ->toArray(),
        ];
    }
}
