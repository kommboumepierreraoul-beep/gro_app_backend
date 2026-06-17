<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisputeMessage extends Model
{
    use HasFactory;

    protected $fillable = ['dispute_id', 'user_id', 'message', 'attachments'];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function dispute()
    {
        return $this->belongsTo(Dispute::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
