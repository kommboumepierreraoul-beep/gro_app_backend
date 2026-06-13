<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'shop_id', 'order_number', 'total_amount', 'status',
        'seller_confirmed_delivery', 'client_confirmed_delivery', 'shipping_address'
    ];

    protected $casts = [
        'seller_confirmed_delivery' => 'boolean',
        'client_confirmed_delivery' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
    
    
}
