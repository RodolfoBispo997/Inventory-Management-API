<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSupplierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
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
            'supplier_product_code' => $this->pivot->supplier_product_code,
        ];
    }
}
