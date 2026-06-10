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

    // Utilisateurs que je suis
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'follows',
            'follower_id',
            'following_id'
        )->withTimestamps();
    }

    // Utilisateurs qui me suivent
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

    public function follows(User $user): bool
    {
        return $this->following()
            ->where('following_id', $user->id)
            ->exists();
    }

    public function missions()
    {
        return $this->hasMany(Mission::class, 'author_id');
    }
   

    // Accesseur pour avatar
    public function getAvatarAttribute()
    {
        return $this->profile?->avatar;
    }
}
