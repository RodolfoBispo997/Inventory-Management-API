<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /** @param array{type: string, quantity: int, reason?: ?string} $data */
    public function record(Product $product, array $data): StockMovement
    {
        return DB::transaction(function () use ($product, $data): StockMovement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($data['type'] === 'OUT' && $lockedProduct->current_stock < $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ['The quantity exceeds the available stock.'],
                ]);
            }

            $lockedProduct->current_stock += $data['type'] === 'IN'
                ? $data['quantity']
                : -$data['quantity'];
            $lockedProduct->save();

            return $lockedProduct->movements()->create($data);
        });
    }
}
