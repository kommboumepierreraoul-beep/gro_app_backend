<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionApplication extends Model
{
    use HasFactory;

    const STATUS_PENDING   = 'pending';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';
    const STATUS_CONFIRMED = 'confirmed';

    const METHOD_FORM        = 'form';
    const METHOD_APP_MESSAGE = 'app_message';
    const METHOD_WHATSAPP    = 'whatsapp';
    const METHOD_EMAIL       = 'email';

    protected $fillable = [
        'mission_id',
        'applicant_id',
        'method',
        'form_responses',
        'motivation',
        'attachment_paths',
        'status',
        'author_note',
        'rejection_reason',
        'accepted_at',
        'rejected_at',
        'withdrawn_at',
        'confirmed_at',
    ];

    protected $casts = [
        'form_responses'   => 'array',
        'attachment_paths' => 'array',
        'accepted_at'      => 'datetime',
        'rejected_at'      => 'datetime',
        'withdrawn_at'     => 'datetime',
        'confirmed_at'     => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    // ── Actions métier ───────────────────────────────────────────────────

    /**
     * Accepter la candidature.
     * Envoie la notification au candidat.
     */
    public function accept(): void
    {
        $this->update([
            'status'      => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'rejected_at' => null,
        ]);

        $this->applicant->notify(new \App\Notifications\Mission\ApplicationAccepted($this));
    }

    /**
     * Refuser la candidature avec raison optionnelle.
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status'           => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'rejected_at'      => now(),
        ]);

        $this->applicant->notify(new \App\Notifications\Mission\ApplicationRejected($this));
    }

    /**
     * Candidat se retire.
     */
    public function withdraw(): void
    {
        $this->update([
            'status'       => self::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }
}
