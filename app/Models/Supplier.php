<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = ['name', 'cnpj', 'email', 'phone'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['price', 'supplier_product_code'])
            ->withTimestamps();
    }
}
