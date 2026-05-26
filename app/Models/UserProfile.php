<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'headline',
        'bio',
        'location',
        'website',
        'banner',
        'avatar',
    ];

    // Ajout de l'attribut virtuel pour l'URL de l'avatar
    protected $appends = ['avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Accessor pour obtenir l'URL complète de l'avatar et gérer le cas où il n'y en a pas
    public function getAvatarUrlAttribute(): string
    {
        return $this->avatar
            ? Storage::url($this->avatar)
            : asset('images/default-avatar.png');
    }
    
}
