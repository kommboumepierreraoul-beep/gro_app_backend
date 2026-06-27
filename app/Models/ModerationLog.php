<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModerationLog extends Model
{
    protected $fillable = [
        'moderatable_type',
        'moderatable_id',
        'content_hash',
        'flagged',
        'confidence_score',
        'reasons',
        'raw_response',
        'status',
        'model_used',
        'processing_time_ms',
    ];

    protected $casts = [
        'flagged'            => 'boolean',
        'confidence_score'   => 'float',
        'reasons'            => 'array',
        'raw_response'       => 'array',
        'processing_time_ms' => 'integer',
    ];

    public function moderatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeFlagged($query)
    {
        return $query->where('flagged', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
