<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class Mission extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constantes ──────────────────────────────────────────────────────

    const STATUS_DRAFT       = 'draft';
    const STATUS_PUBLISHED   = 'published';
    const STATUS_FILLED      = 'filled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_ARCHIVED    = 'archived';
    const STATUS_SUSPENDED   = 'suspended';
    const STATUS_CANCELLED   = 'cancelled';

    const REMUNERATION_FIXED      = 'fixed';
    const REMUNERATION_DAILY      = 'daily_rate';
    const REMUNERATION_HOURLY     = 'hourly_rate';
    const REMUNERATION_NEGOTIABLE = 'negotiable';
    const REMUNERATION_IN_KIND    = 'in_kind';
    const REMUNERATION_VOLUNTEER  = 'volunteer';

    const DURATION_HOURS    = 'hours';
    const DURATION_DAY      = 'day';
    const DURATION_DAYS     = 'days';
    const DURATION_WEEKS    = 'weeks';
    const DURATION_FLEXIBLE = 'flexible';

    const SCOPE_RADIUS   = 'radius';
    const SCOPE_PLATFORM = 'platform';

    // ── Fillable ─────────────────────────────────────────────────────────

    protected $fillable = [
        'ulid',
        'author_id',
        'category_id',
        'title',
        'description',
        'desired_profile',
        'duration_type',
        'duration_value',
        'start_date',
        'expires_at',
        'location_label',
        'location_point',
        'diffusion_radius_km',
        'diffusion_scope',
        'remuneration_type',
        'remuneration_amount',
        'remuneration_currency',
        'remuneration_conditions',
        'contact_methods',
        'application_form',
        'allow_attachments',
        'max_applications',
        'status',
        'filled_at',
        'completed_at',
    ];

    protected $casts = [
        'contact_methods'     => 'array',
        'application_form'    => 'array',
        'start_date'          => 'date',
        'expires_at'          => 'datetime',
        'filled_at'           => 'datetime',
        'completed_at'        => 'datetime',
        'allow_attachments'   => 'boolean',
        'remuneration_amount' => 'decimal:2',
    ];

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Auto-générer ULID à la création
        static::creating(function (self $mission) {
            $mission->ulid ??= (string) Str::ulid();
        });
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MissionCategory::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(MissionApplication::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MissionReview::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(MissionView::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(MissionReminder::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeActive($query)
    {
        return $query->published()->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Filtre par rayon géographique (PostGIS ST_DWithin).
     * $radiusKm : rayon en kilomètres
     */
    public function scopeNearby($query, float $lat, float $lng, int $radiusKm = 25)
    {
        return $query->whereRaw(
            "ST_DWithin(
                location_point::geography,
                ST_MakePoint(?, ?)::geography,
                ?
            )",
            [$lng, $lat, $radiusKm * 1000]
        );
    }

    /**
     * Ajoute la distance (km) depuis un point donné dans la sélection.
     * Utilisé pour le tri et l'affichage "à X km".
     */
    public function scopeWithDistance($query, float $lat, float $lng)
    {
        return $query->selectRaw(
            "missions.*, ROUND(
                (ST_Distance(
                    location_point::geography,
                    ST_MakePoint(?, ?)::geography
                ) / 1000)::numeric, 1
            ) AS distance_km",
            [$lng, $lat]
        );
    }

    public function scopeForAuthor($query, int $userId)
    {
        return $query->where('author_id', $userId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isOwnedBy(User $user): bool
    {
        return $this->author_id === $user->id;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->max_applications === null
                || $this->applications_count < $this->max_applications)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isFull(): bool
    {
        return $this->max_applications !== null
            && $this->applications_count >= $this->max_applications;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_SUSPENDED,
        ]);
    }

    /**
     * Définir la colonne PostGIS location_point depuis lat/lng.
     * Appeler avant save().
     */
    public function setLocationPoint(float $lat, float $lng): void
    {
        DB::statement(
            "UPDATE missions SET location_point = ST_MakePoint(?, ?)::geography WHERE id = ?",
            [$lng, $lat, $this->id]
        );
    }

    /**
     * Récupérer lat/lng depuis la colonne PostGIS.
     */
    public function getLatLng(): ?array
    {
        if (!$this->id) return null;

        $row = DB::selectOne(
            "SELECT ST_Y(location_point::geometry) as lat,
                    ST_X(location_point::geometry) as lng
             FROM missions WHERE id = ?",
            [$this->id]
        );

        return $row ? ['lat' => (float)$row->lat, 'lng' => (float)$row->lng] : null;
    }

    /**
     * Texte affiché pour la rémunération.
     */
    public function getRemunerationLabelAttribute(): string
    {
        $labels = [
            self::REMUNERATION_FIXED      => 'Montant fixe',
            self::REMUNERATION_DAILY      => 'Taux journalier',
            self::REMUNERATION_HOURLY     => 'Taux horaire',
            self::REMUNERATION_NEGOTIABLE => 'À négocier',
            self::REMUNERATION_IN_KIND    => 'En nature',
            self::REMUNERATION_VOLUNTEER  => 'Bénévolat',
        ];

        if ($this->remuneration_amount) {
            return number_format($this->remuneration_amount, 0, ',', ' ')
                . ' ' . $this->remuneration_currency;
        }

        return $labels[$this->remuneration_type] ?? $this->remuneration_type;
    }

    /**
     * Texte affiché pour la durée.
     */
    public function getDurationLabelAttribute(): string
    {
        $labels = [
            self::DURATION_HOURS    => 'heure(s)',
            self::DURATION_DAY      => 'journée',
            self::DURATION_DAYS     => 'jour(s)',
            self::DURATION_WEEKS    => 'semaine(s)',
            self::DURATION_FLEXIBLE => 'Durée flexible',
        ];

        if ($this->duration_type === self::DURATION_FLEXIBLE) {
            return 'Durée flexible';
        }

        return $this->duration_value . ' ' . ($labels[$this->duration_type] ?? '');
    }
}
