<?php
// app/Models/ModerationLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<string>
     */
    protected $fillable = [
        'moderatable_type',
        'moderatable_id',
        'is_safe',
        'score',
        'categories',
        'reason',
        'action',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Les attributs qui doivent être castés.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_safe' => 'boolean',
        'score' => 'float',
        'categories' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Les valeurs par défaut des attributs.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_safe' => true,
        'score' => 0.0,
        'action' => 'approved',
    ];

    /**
     * Relation polymorphique avec le contenu modéré (Post, Comment, etc.)
     */
    public function moderatable()
    {
        return $this->morphTo();
    }

    /**
     * Relation avec l'utilisateur qui a effectué la revue
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope pour les logs approuvés
     */
    public function scopeApproved($query)
    {
        return $query->where('action', 'approved');
    }

    /**
     * Scope pour les logs signalés
     */
    public function scopeFlagged($query)
    {
        return $query->where('action', 'flagged');
    }

    /**
     * Scope pour les logs rejetés
     */
    public function scopeRejected($query)
    {
        return $query->where('action', 'rejected');
    }

    /**
     * Scope pour les contenus non sûrs
     */
    public function scopeUnsafe($query)
    {
        return $query->where('is_safe', false);
    }

    /**
     * Scope pour les contenus sûrs
     */
    public function scopeSafe($query)
    {
        return $query->where('is_safe', true);
    }

    /**
     * Déterminer si le log est un signalement
     */
    public function isFlagged(): bool
    {
        return $this->action === 'flagged';
    }

    /**
     * Déterminer si le log est un rejet
     */
    public function isRejected(): bool
    {
        return $this->action === 'rejected';
    }

    /**
     * Déterminer si le log est approuvé
     */
    public function isApproved(): bool
    {
        return $this->action === 'approved';
    }

    /**
     * Vérifier si le contenu a été revu
     */
    public function isReviewed(): bool
    {
        return !is_null($this->reviewed_at) && !is_null($this->reviewed_by);
    }

    /**
     * Obtenir les catégories sous forme de tableau
     */
    public function getCategoriesListAttribute(): array
    {
        return $this->categories ?? [];
    }

    /**
     * Définir les catégories depuis un tableau
     */
    public function setCategoriesListAttribute(array $categories): void
    {
        $this->categories = $categories;
    }
}
