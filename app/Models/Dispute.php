<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'user_id', 'seller_id', 'reason', 'description', 'attachments',
        'status', 'seller_response', 'seller_attachments', 'resolved_by',
        'admin_notes', 'resolution', 'refund_amount', 'mode', 'escalated_at', 'admin_question'
    ];

    protected $casts = [
        'attachments'       => 'array',
        'seller_attachments' => 'array',
        'refund_amount'     => 'decimal:2',
        'escalated_at'      => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
