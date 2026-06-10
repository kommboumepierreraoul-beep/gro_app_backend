<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionReview extends Model
{
    const UPDATED_AT = null; // pas de updated_at

    const DIR_AUTHOR_TO_APPLICANT = 'author_to_applicant';
    const DIR_APPLICANT_TO_AUTHOR = 'applicant_to_author';

    protected $fillable = [
        'mission_id',
        'reviewer_id',
        'reviewee_id',
        'direction',
        'rating',
        'comment',
    ];

    protected $casts = ['rating' => 'integer'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
