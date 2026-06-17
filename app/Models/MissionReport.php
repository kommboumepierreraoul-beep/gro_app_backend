<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReport extends Model
{
    const REASON_SPAM          = 'spam';
    const REASON_INAPPROPRIATE = 'inappropriate';
    const REASON_SCAM          = 'scam';
    const REASON_DUPLICATE     = 'duplicate';
    const REASON_MISLEADING    = 'misleading';
    const REASON_OTHER         = 'other';

    const STATUS_PENDING      = 'pending';
    const STATUS_REVIEWED     = 'reviewed';
    const STATUS_DISMISSED    = 'dismissed';
    const STATUS_ACTION_TAKEN = 'action_taken';

    protected $fillable = [
        'mission_id',
        'reporter_id',
        'reason',
        'details',
        'status',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_SPAM          => 'Spam ou publicité',
            self::REASON_INAPPROPRIATE => 'Contenu inapproprié',
            self::REASON_SCAM          => 'Arnaque suspectée',
            self::REASON_DUPLICATE     => 'Mission en doublon',
            self::REASON_MISLEADING    => 'Description trompeuse',
            self::REASON_OTHER         => 'Autre',
            default                    => $reason,
        };
    }
}
