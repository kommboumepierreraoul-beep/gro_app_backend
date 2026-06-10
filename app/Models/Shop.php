<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $logo
 * @property string|null $banner
 * @property string|null $address
 * @property string|null $city
 * @property string|null $phone
 * @property string $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereBanner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Shop whereUserId($value)
 * @mixin \Eloquent
 */
class Shop extends Model
{
    protected $fillable = [
        'user_id', 'name', 'slug', 'description',
        'logo', 'banner', 'address', 'city', 'phone', 'status'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
