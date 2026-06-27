<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne, BelongsToMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use App\Models\Transaction;
/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $role
 * @property string|null $phone
 * @property string|null $gender
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $deleted_at
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Announcement> $announcements
 * @property-read int|null $announcements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunityNotification> $communityNotifications
 * @property-read int|null $community_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $followers
 * @property-read int|null $followers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $following
 * @property-read int|null $following_count
 * @property-read string $full_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Like> $likes
 * @property-read int|null $likes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message> $messages
 * @property-read int|null $messages_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order> $orders
 * @property-read int|null $orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Post> $posts
 * @property-read int|null $posts_count
 * @property-read \App\Models\UserProfile|null $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductReview> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\Shop|null $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\Wallet|null $wallet
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Wishlist> $wishlist
 * @property-read int|null $wishlist_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User admins()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User fournisseurs()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User users()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastname($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_FOURNISSEUR = 'fournisseur';

    protected $fillable = ['firstname', 'lastname', 'email', 'password', 'phone', 'role', 'status', 'email_verified_at', 'is_admin'];
    protected $hidden = ['password', 'remember_token'];
    protected $appends = ['full_name'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean'];


    public function getFullNameAttribute(): string { return "{$this->firstname} {$this->lastname}"; }

    public function profile(): HasOne { return $this->hasOne(UserProfile::class); }
    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function likes(): HasMany { return $this->hasMany(Like::class); }
    public function announcements(): HasMany { return $this->hasMany(Announcement::class); }

    public function following(): BelongsToMany {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
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

    public function conversations(): BelongsToMany {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function messages(): HasMany { return $this->hasMany(Message::class, 'sender_id'); }
    public function communityNotifications(): HasMany { return $this->hasMany(CommunityNotification::class); }

    public function scopeAdmins($query) { return $query->where('role', self::ROLE_ADMIN); }
    public function scopeUsers($query) { return $query->where('role', self::ROLE_USER); }
    public function scopeFournisseurs($query) { return $query->where('role', self::ROLE_FOURNISSEUR); }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN || $this->is_admin === true; }
    public function isUser(): bool { return $this->role === self::ROLE_USER; }
    public function isFournisseur(): bool { return $this->role === self::ROLE_FOURNISSEUR; }

    public function follows(User $user): bool {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function shop(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Shop::class); }
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Order::class); }
    public function wishlist(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Wishlist::class); }
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ProductReview::class); }

<<<<<<< HEAD
public function wallet()
{
    return $this->hasOne(Wallet::class);
=======
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
>>>>>>> origin/main
}

public function transactions()
{
    return $this->hasMany(Transaction::class);
    
}
public function walletTransactions() { return $this->hasMany(WalletTransaction::class); }

// Au lieu de return $this->wallet_balance;
public function getWalletBalanceAttribute()
{
    return $this->wallet ? $this->wallet->balance : 0;
}
}


