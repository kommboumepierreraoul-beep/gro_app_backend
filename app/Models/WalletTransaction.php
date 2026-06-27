<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'type',
        'description',
        'status',
        'reference',
        'balance_before',
        'balance_after',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}