<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sku', 'minimum_stock'];

    protected $attributes = ['current_stock' => 0, 'minimum_stock' => 0];

    protected function casts(): array
    {
        return ['minimum_stock' => 'integer', 'current_stock' => 'integer'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->withPivot(['price', 'supplier_product_code'])
            ->withTimestamps();
    }
}
