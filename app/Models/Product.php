<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'slug', 'description',
        'price', 'unit_price', 'weight', 'stock', 'stock_quantity', 'images',
        'is_featured', 'status', 'approval_status', 'rejection_reason',
        'user_id', 'listing_type', 'delivery_condition', 'variety', 'origin',
        'certification', 'harvest_date', 'expiration_date'
    ];
    protected $casts = [
        'images' => 'array',
    ];
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function getAverageRatingAttribute(): float
    {
        return round((float) $this->reviews()->avg('rating'), 1);
    }
}
