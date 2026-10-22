<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsumptionReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     department_id: int|null,
     *     departments: list<array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         consumed_quantity: int,
     *         movement_count: int,
     *         products: list<array{
     *             id: int,
     *             name: string,
     *             sku: string,
     *             consumed_quantity: int,
     *             movement_count: int
     *         }>
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
