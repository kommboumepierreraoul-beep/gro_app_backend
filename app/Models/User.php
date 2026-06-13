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

    protected $fillable = ['firstname', 'lastname', 'email', 'password', 'phone', 'role', 'status', 'email_verified_at'];
    protected $hidden = ['password', 'remember_token'];
    protected $appends = ['full_name'];
    protected $casts = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    public function getFullNameAttribute(): string { return "{$this->firstname} {$this->lastname}"; }

    public function profile(): HasOne { return $this->hasOne(UserProfile::class); }
    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
    public function likes(): HasMany { return $this->hasMany(Like::class); }
    public function announcements(): HasMany { return $this->hasMany(Announcement::class); }

    public function following(): BelongsToMany {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function followers(): BelongsToMany {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function conversations(): BelongsToMany {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function messages(): HasMany { return $this->hasMany(Message::class, 'sender_id'); }
    public function communityNotifications(): HasMany { return $this->hasMany(CommunityNotification::class); }

    public function scopeAdmins($query) { return $query->where('role', self::ROLE_ADMIN); }
    public function scopeUsers($query) { return $query->where('role', self::ROLE_USER); }
    public function scopeFournisseurs($query) { return $query->where('role', self::ROLE_FOURNISSEUR); }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isUser(): bool { return $this->role === self::ROLE_USER; }
    public function isFournisseur(): bool { return $this->role === self::ROLE_FOURNISSEUR; }

    public function follows(User $user): bool {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function shop(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Shop::class); }
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Order::class); }
    public function wishlist(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(Wishlist::class); }
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ProductReview::class); }

public function wallet()
{
    return $this->hasOne(Wallet::class);
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


