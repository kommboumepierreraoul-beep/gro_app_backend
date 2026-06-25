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
        'password'          => 'hashed',
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
    // MODERATION - RELATIONS
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
     * Récupère les modérations des commentaires de l'utilisateur
     */
    public function moderationComments(): HasMany
    {
        return $this->hasMany(ModerationComment::class, 'comment_id', 'id')
            ->whereHas('comment', function ($query) {
                $query->where('user_id', $this->id);
            });
    }

    /**
     * Récupère les modérations des messages de l'utilisateur
     */
    public function moderationMessages(): HasMany
    {
        return $this->hasMany(ModerationMessage::class, 'message_id', 'id')
            ->whereHas('message', function ($query) {
                $query->where('sender_id', $this->id);
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
    // HELPERS
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
    // MODERATION - HELPERS
    // -------------------------------------------------------------------------

    /**
     * Récupère tous les contenus en attente de modération de l'utilisateur
     */
    public function getPendingContentCount(): int
    {
        $pendingPosts = $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })->count();

        $pendingComments = $this->comments()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })->count();

        $pendingMessages = $this->messages()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })->count();

        return $pendingPosts + $pendingComments + $pendingMessages;
    }

    /**
     * Vérifie si l'utilisateur a des contenus rejetés
     */
    public function hasRejectedContent(): bool
    {
        return $this->posts()->whereHas('moderation', function ($query) {
            $query->where('status', 'rejected');
        })->exists() ||
            $this->comments()->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })->exists() ||
            $this->messages()->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })->exists();
    }

    /**
     * Récupère le taux de rejet de l'utilisateur
     */
    public function getRejectionRate(): float
    {
        $total = $this->posts()->whereHas('moderation')->count() +
            $this->comments()->whereHas('moderation')->count() +
            $this->messages()->whereHas('moderation')->count();

        if ($total === 0) {
            return 0;
        }

        $rejected = $this->posts()->whereHas('moderation', function ($query) {
            $query->where('status', 'rejected');
        })->count() +
            $this->comments()->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })->count() +
            $this->messages()->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
            })->count();

        return round(($rejected / $total) * 100, 2);
    }

    /**
     * Vérifie si l'utilisateur peut publier (pas trop de rejets)
     */
    public function canPublish(): bool
    {
        $rejectionRate = $this->getRejectionRate();

        // Si le taux de rejet dépasse 30%, l'utilisateur est limité
        if ($rejectionRate > 30) {
            return false;
        }

        // Vérifier si l'utilisateur a des contenus en attente depuis plus de 24h
        $pendingSince = $this->posts()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'pending');
            })
            ->where('created_at', '<', now()->subHours(24))
            ->count();

        if ($pendingSince > 5) {
            return false;
        }

        return true;
    }
}
