<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class ProductSupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     supplier: array{id: int, name: string, cnpj: string},
     *     price: string,
     *     supplier_product_code: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'supplier' => [
                'id' => $this->id,
                'name' => $this->name,
                'cnpj' => $this->cnpj,
            ],
            'price' => number_format((float) $this->pivot->price, 2, '.', ''),
            'supplier_product_code' => $this->pivot->supplier_product_code === null
                ? null
                : (string) $this->pivot->supplier_product_code,
        ];
    }
}