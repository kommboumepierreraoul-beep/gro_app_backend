<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_id', 'category_id', 'name', 'slug', 'description',
        'price', 'weight', 'stock', 'images', 'is_featured', 'status'
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
}
