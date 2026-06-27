<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MissionCategory extends Model
{
    protected $fillable = ['name', 'slug', 'icon', 'color', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
