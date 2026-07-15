<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'user_id', 'seller_id', 'shop_id', 'total_amount', 'status',
        'shipping_address', 'payment_method', 'payment_status', 'payment_reference',
        'client_confirmed_delivery', 'seller_confirmed_delivery',
        'delivery_latitude', 'delivery_longitude'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'client_confirmed_delivery' => 'boolean',
        'seller_confirmed_delivery' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
