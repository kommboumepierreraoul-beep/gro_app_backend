<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{
    HasMany,
    HasOne,
    BelongsToMany
};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    // -------------------------------------------------------------------------
    // CONSTANTES
    // -------------------------------------------------------------------------

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_FOURNISSEUR = 'fournisseur';

    // -------------------------------------------------------------------------
    // FILLABLE
    // -------------------------------------------------------------------------

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'phone',
        'role',
        'email_verified_at',
        'publishing_blocked_until',
    ];

    // -------------------------------------------------------------------------
    // HIDDEN
    // -------------------------------------------------------------------------

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // -------------------------------------------------------------------------
    // APPENDS
    // -------------------------------------------------------------------------

    protected $appends = [
        'full_name',
    ];

    // -------------------------------------------------------------------------
    // CASTS
    // -------------------------------------------------------------------------

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'publishing_blocked_until' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // ACCESSORS
    // -------------------------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    public function getAvatarAttribute()
    {
        return $this->profile?->avatar;
    }

    // -------------------------------------------------------------------------
    // RELATIONS
    // -------------------------------------------------------------------------

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    // -------------------------------------------------------------------------
    // FOLLOW SYSTEM
    // -------------------------------------------------------------------------

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'follower_id',
            'following_id'
        )->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'following_id',
            'follower_id'
        )->withTimestamps();
    }

    // -------------------------------------------------------------------------
    // MESSAGING
    // -------------------------------------------------------------------------

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // -------------------------------------------------------------------------
    // NOTIFICATIONS
    // -------------------------------------------------------------------------

    public function communityNotifications(): HasMany
    {
        return $this->hasMany(CommunityNotification::class);
    }

    // -------------------------------------------------------------------------
    // AI CONVERSATIONS
    // -------------------------------------------------------------------------

    public function aiConversations(): HasMany
    {
        return $this->hasMany(AiConversation::class, 'user_id')
            ->orderBy('updated_at', 'desc');
    }

    public function aimessageconversations(): HasMany
    {
        return $this->aiConversations();
    }

    // -------------------------------------------------------------------------
    // MISSIONS
    // -------------------------------------------------------------------------

    public function missions()
    {
        return $this->hasMany(Mission::class, 'author_id');
    }

    // -------------------------------------------------------------------------
    // MODERATION - RELATIONS (UNIQUEMENT POUR LES POSTS)
    // -------------------------------------------------------------------------

    /**
     * Récupère les modérations des posts de l'utilisateur
     */
    public function moderationPosts(): HasMany
    {
        return $this->hasMany(ModerationPost::class, 'post_id', 'id')
            ->whereHas('post', function ($query) {
                $query->where('user_id', $this->id);
            });
    }

    /**
     * Récupère les décisions de modération prises par l'utilisateur (en tant que modérateur)
     */
    public function moderationDecisions(): HasMany
    {
        return $this->hasMany(ModerationAuditLog::class, 'actor_id')
            ->where('actor_type', 'moderator');
    }

    /**
     * Récupère les signalements faits par l'utilisateur
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'reporter_id');
    }

    /**
     * Récupère les signalements résolus par l'utilisateur (en tant que modérateur)
     */
    public function resolvedReports(): HasMany
    {
        return $this->hasMany(ModerationReport::class, 'resolved_by');
    }

    // -------------------------------------------------------------------------
    // SCOPES
    // -------------------------------------------------------------------------

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeUsers($query)
    {
        return $query->where('role', self::ROLE_USER);
    }

    public function scopeFournisseurs($query)
    {
        return $query->where('role', self::ROLE_FOURNISSEUR);
    }

    // -------------------------------------------------------------------------
    // HELPERS - RÔLES
    // -------------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isFournisseur(): bool
    {
        return $this->role === self::ROLE_FOURNISSEUR;
    }

    public function isModerator(): bool
    {
        return $this->isAdmin();
    }

    public function follows(User $user): bool
    {
        return $this->following()
            ->where('following_id', $user->id)
            ->exists();
    }

    public function hasAiConversations(): bool
    {
        return $this->aiConversations()->count() > 0;
    }

    public function getLatestAiConversation()
    {
        return $this->aiConversations()->first();
    }

    // -------------------------------------------------------------------------
    // MODERATION - STATISTIQUES DE BASE
    // -------------------------------------------------------------------------

    /**
     * Récupère le nombre de posts rejetés
     */
    public function getRejectedPostsCount(): int
    {
        return $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })
            ->count();
    }

    /**
     * Récupère le nombre de posts en attente
     */
    public function getPendingPostsCount(): int
    {
        return $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->count();
    }

    /**
     * Récupère le nombre de posts approuvés
     */
    public function getApprovedPostsCount(): int
    {
        return $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->count();
    }

    /**
     * Récupère le taux de rejet
     */
    public function getRejectionRate(): float
    {
        $total = $this->posts()
            ->whereHas('moderation')
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $rejected = $this->getRejectedPostsCount();
        return round(($rejected / $total) * 100, 2);
    }

    /**
     * Récupère les statistiques complètes de modération
     */
    public function getModerationStats(): array
    {
        $total = $this->posts()
            ->whereHas('moderation')
            ->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'review' => 0,
                'rejection_rate' => 0,
                'consecutive_rejected' => 0,
                'pending_since_24h' => 0,
                'can_publish' => true,
            ];
        }

        $pending = $this->getPendingPostsCount();
        $approved = $this->getApprovedPostsCount();
        $rejected = $this->getRejectedPostsCount();

        $review = $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'review');
            })
            ->count();

        // Compter les rejets consécutifs
        $consecutiveRejected = 0;
        $latestPosts = $this->posts()
            ->whereHas('moderation')
            ->with('moderation')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($latestPosts as $post) {
            if ($post->moderation && $post->moderation->status === 'rejected') {
                $consecutiveRejected++;
            } else {
                break;
            }
        }

        // Compter les posts en attente depuis plus de 24h
        $pendingSince24h = $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'review' => $review,
            'rejection_rate' => round(($rejected / $total) * 100, 2),
            'consecutive_rejected' => $consecutiveRejected,
            'pending_since_24h' => $pendingSince24h,
            'can_publish' => $this->canPublish(),
        ];
    }

    // -------------------------------------------------------------------------
    // MODERATION - BLOCAGE
    // -------------------------------------------------------------------------

    /**
     * Vérifie si l'utilisateur est temporairement bloqué
     */
    public function isPublishingBlocked(): bool
    {
        if (!$this->publishing_blocked_until) {
            return false;
        }

        if (now()->gt($this->publishing_blocked_until)) {
            $this->publishing_blocked_until = null;
            $this->save();
            return false;
        }

        return true;
    }

    /**
     * Bloque la publication de l'utilisateur jusqu'à une date donnée
     */
    public function blockPublishing($until): void
    {
        $this->publishing_blocked_until = $until;
        $this->save();
    }

    /**
     * Débloque la publication de l'utilisateur
     */
    public function unblockPublishing(): void
    {
        $this->publishing_blocked_until = null;
        $this->save();
    }

    /**
     * Récupère la date de fin de blocage
     */
    public function getPublishingBlockedUntil(): ?string
    {
        if (!$this->publishing_blocked_until) {
            return null;
        }

        // Si la date est passée, débloquer automatiquement
        if (now()->gt($this->publishing_blocked_until)) {
            $this->publishing_blocked_until = null;
            $this->save();
            return null;
        }

        return $this->publishing_blocked_until->toISOString();
    }

    /**
     * Récupère le temps restant de blocage (format lisible)
     */
    public function getRemainingBlockTime(): ?string
    {
        if (!$this->publishing_blocked_until) {
            return null;
        }

        $diff = now()->diffInMinutes($this->publishing_blocked_until, false);

        if ($diff <= 0) {
            $this->publishing_blocked_until = null;
            $this->save();
            return null;
        }

        if ($diff < 60) {
            return $diff . ' minutes';
        }

        $hours = floor($diff / 60);
        $minutes = $diff % 60;

        if ($minutes === 0) {
            return $hours . 'h';
        }

        return $hours . 'h ' . $minutes . 'min';
    }

    // -------------------------------------------------------------------------
    // MODERATION - CAN PUBLISH
    // -------------------------------------------------------------------------

    /**
     * Vérifie si l'utilisateur peut publier
     */
    public function canPublish(): bool
    {
        // Vérifier si l'utilisateur est temporairement bloqué
        if ($this->isPublishingBlocked()) {
            return false;
        }

        // Récupérer les statistiques
        $rejectedCount = $this->getRejectedPostsCount();
        $pendingCount = $this->getPendingPostsCount();
        $rejectionRate = $this->getRejectionRate();

        // ⛔ Si plus de 10 posts rejetés → bloqué
        if ($rejectedCount >= 10) {
            return false;
        }

        // ⛔ Si plus de 5 posts rejetés ET plus de 5 en attente → limité
        if ($rejectedCount >= 5 && $pendingCount >= 5) {
            return false;
        }

        // ⛔ Si plus de 10 posts en attente → limité
        if ($pendingCount >= 10) {
            return false;
        }

        // ✅ Vérifier le taux de rejet
        if ($rejectionRate > 30) {
            return false;
        }

        // ✅ Vérifier les posts en attente depuis plus de 24h
        $pendingSince = $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', ['pending', 'review']);
            })
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        if ($pendingSince > 5) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur est bloqué (définitivement)
     */
    public function isBlocked(): bool
    {
        return $this->getRejectedPostsCount() >= 10;
    }

    // -------------------------------------------------------------------------
    // MODERATION - RAISONS ET MESSAGES
    // -------------------------------------------------------------------------

    /**
     * Récupère les raisons du blocage
     */
    public function getBlockReasons(): array
    {
        $reasons = [];
        $stats = $this->getModerationStats();

        $rejectedCount = $stats['rejected'];
        $pendingCount = $stats['pending'];
        $rejectionRate = $stats['rejection_rate'];
        $consecutiveRejected = $stats['consecutive_rejected'];
        $pendingSince24h = $stats['pending_since_24h'];

        // ⛔ Plus de 10 posts rejetés
        if ($rejectedCount >= 10) {
            $reasons[] = [
                'reason' => "Vous avez {$rejectedCount} posts rejetés (limite: 10)",
                'type' => 'permanent',
                'action' => 'Contactez un administrateur pour résoudre ce problème.',
            ];
        }

        // ⛔ Plus de 5 rejets ET plus de 5 en attente
        if ($rejectedCount >= 5 && $pendingCount >= 5) {
            $reasons[] = [
                'reason' => "Vous avez {$rejectedCount} rejets et {$pendingCount} posts en attente",
                'type' => 'temporary',
                'action' => "Attendez que vos posts en attente soient modérés.",
            ];
        }

        // ⛔ Plus de 10 posts en attente
        if ($pendingCount >= 10) {
            $reasons[] = [
                'reason' => "Vous avez {$pendingCount} posts en attente (limite: 10)",
                'type' => 'temporary',
                'action' => "Attendez que vos posts soient modérés avant d'en publier de nouveaux.",
            ];
        }

        // ⛔ Taux de rejet > 30%
        if ($rejectionRate > 30 && $stats['total'] >= 5) {
            $reasons[] = [
                'reason' => "Taux de rejet de {$rejectionRate}% sur les {$stats['total']} derniers posts (limite: 30%)",
                'type' => 'temporary',
                'action' => "Publiez du contenu de meilleure qualité pour faire baisser votre taux de rejet.",
            ];
        }

        // ⛔ Plus de 5 posts en attente depuis plus de 24h
        if ($pendingSince24h > 5) {
            $reasons[] = [
                'reason' => "{$pendingSince24h} posts en attente depuis plus de 24h",
                'type' => 'temporary',
                'action' => "Une modération manuelle est en cours. Merci de patienter.",
            ];
        }

        // ⛔ Rejets consécutifs (>= 3)
        if ($consecutiveRejected >= 3) {
            $reasons[] = [
                'reason' => "{$consecutiveRejected} rejets consécutifs",
                'type' => 'temporary',
                'action' => "Prenez le temps de revoir la qualité de vos publications.",
            ];
        }

        // Si l'utilisateur est bloqué temporairement
        if ($this->isPublishingBlocked()) {
            $reasons[] = [
                'reason' => "Blocage temporaire jusqu'au " . $this->publishing_blocked_until->format('d/m/Y H:i'),
                'type' => 'temporary',
                'action' => "Attendez la fin du blocage pour pouvoir publier à nouveau.",
            ];
        }

        // Si aucune raison, l'utilisateur peut publier
        if (empty($reasons)) {
            $reasons[] = [
                'reason' => 'Vous êtes autorisé à publier',
                'type' => 'allowed',
                'action' => 'Vous pouvez créer de nouvelles publications.',
            ];
        }

        return $reasons;
    }

    /**
     * Récupère le message de statut de modération
     */
    public function getModerationStatusMessage(): string
    {
        $stats = $this->getModerationStats();

        if ($this->isBlocked()) {
            return '⚠️ Votre compte a été bloqué en raison de trop nombreux contenus rejetés.';
        }

        if ($stats['rejected'] > 5) {
            return '⚠️ Attention : Vous avez ' . $stats['rejected'] . ' publications rejetées.';
        }

        if ($stats['pending'] > 5) {
            return '⏳ Vous avez ' . $stats['pending'] . ' publications en attente de modération.';
        }

        if ($stats['approved'] > 0) {
            return '✅ ' . $stats['approved'] . ' publications approuvées.';
        }

        return '📝 Aucune publication modérée.';
    }

    /**
     * Estime le temps d'attente avant de pouvoir reposter
     */
    public function getEstimatedWaitTime(): ?string
    {
        // Si l'utilisateur peut publier, pas d'attente
        if ($this->canPublish()) {
            return null;
        }

        // Si l'utilisateur a un blocage permanent
        $rejectedCount = $this->getRejectedPostsCount();
        if ($rejectedCount >= 10) {
            return 'Indéterminé - Contactez un administrateur';
        }

        // Vérifier le blocage temporaire
        if ($this->isPublishingBlocked() && $this->publishing_blocked_until) {
            $diff = now()->diffInMinutes($this->publishing_blocked_until, false);

            if ($diff > 0) {
                if ($diff < 60) {
                    return $diff . ' minutes';
                }
                $hours = floor($diff / 60);
                $minutes = $diff % 60;
                return $hours . 'h ' . $minutes . 'min';
            }
        }

        // Calcul basé sur le nombre de posts en attente
        $pendingCount = $this->getPendingPostsCount();

        if ($pendingCount === 0) {
            return 'Très prochainement';
        }

        // Estimation: 1h par post en attente (à adapter selon votre modération)
        $hours = $pendingCount;

        if ($hours <= 1) {
            return 'Moins d\'une heure';
        }

        if ($hours < 24) {
            return "Environ {$hours} heures";
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        if ($days === 1) {
            return "1 jour et {$remainingHours} heures";
        }

        return "{$days} jours et {$remainingHours} heures";
    }

    /**
     * Vérifie si l'utilisateur a des posts rejetés
     */
    public function hasRejectedPosts(): bool
    {
        return $this->getRejectedPostsCount() > 0;
    }

    /**
     * Vérifie si l'utilisateur a des posts en attente
     */
    public function hasPendingPosts(): bool
    {
        return $this->getPendingPostsCount() > 0;
    }

    /**
     * Récupère le nombre total de posts
     */
    public function getTotalPostsCount(): int
    {
        return $this->posts()->count();
    }

    /**
     * Récupère le nombre total de posts modérés
     */
    public function getModeratedPostsCount(): int
    {
        return $this->posts()
            ->whereHas('moderation')
            ->count();
    }
}
