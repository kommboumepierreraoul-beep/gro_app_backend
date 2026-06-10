<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $shop_id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property numeric $price
 * @property numeric|null $weight
 * @property int $stock
 * @property array<array-key, mixed>|null $images
 * @property bool $is_featured
 * @property string $status
 * @property string $listing_type
 * @property numeric|null $unit_price
 * @property int|null $stock_quantity
 * @property string|null $delivery_condition
 * @property string|null $variety
 * @property string|null $origin
 * @property string|null $certification
 * @property \Illuminate\Support\Carbon|null $harvest_date
 * @property \Illuminate\Support\Carbon|null $expiration_date
 * @property string $approval_status
 * @property string|null $rejection_reason
 * @property-read \App\Models\Category|null $category
 * @property-read float $average_rating
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductReview> $reviews
 * @property-read int|null $reviews_count
 * @property-read \App\Models\Shop $shop
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Wishlist> $wishlists
 * @property-read int|null $wishlists_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereApprovalStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCertification($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDeliveryCondition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereExpirationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereHarvestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereListingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereShopId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereStockQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereVariety($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereWeight($value)
 * @mixin \Eloquent
 */
class Product extends Model
{
    protected $fillable = [
    'shop_id',
    'category_id',

    'name',
    'slug',
    'description',

    'price',
    'unit_price',

    'weight',

    'stock',
    'stock_quantity',

    'images',
    'slug',

    'listing_type',
    'delivery_condition',

    'variety',
    'origin',
    'certification',
    'harvest_date',
    'expiration_date',

    'is_featured',
    'status',
    'approval_status',
    'rejection_reason',
];

    protected $casts = [
    'images' => 'array',
    'is_featured' => 'boolean',

    'price' => 'decimal:2',
    'unit_price' => 'decimal:2',

    'harvest_date' => 'date',
    'expiration_date' => 'date',
];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function getAverageRatingAttribute(): float
    {
        return round($this->reviews()->avg('rating') ?? 0, 1);
    }

}
