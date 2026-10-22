<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Notifications\StockMinimumReachedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /** @param array{type: string, quantity: int, reason?: ?string} $data */
    public function record(Product $product, array $data): StockMovement
    {
        return DB::transaction(function () use ($product, $data): StockMovement {
            $lockedProduct = Product::query()
                ->where('organization_id', $product->organization_id)
                ->lockForUpdate()
                ->findOrFail($product->id);

            if ($data['type'] === 'OUT' && $lockedProduct->current_stock < $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => ['The quantity exceeds the available stock.'],
                ]);
            }

            $previousStock = $lockedProduct->current_stock;
            $lockedProduct->current_stock += $data['type'] === 'IN'
                ? $data['quantity']
                : -$data['quantity'];
            $lockedProduct->save();

            $movement = $lockedProduct->movements()->create([
                ...$data,
                'organization_id' => $lockedProduct->organization_id,
            ]);

            $alertRecipient = config('services.stock_alert.email');
            $crossedMinimumStock = $data['type'] === 'OUT'
                && $previousStock > $lockedProduct->minimum_stock
                && $lockedProduct->current_stock <= $lockedProduct->minimum_stock;

            if ($crossedMinimumStock && is_string($alertRecipient) && $alertRecipient !== '') {
                $notification = new StockMinimumReachedNotification(
                    productName: $lockedProduct->name,
                    sku: $lockedProduct->sku,
                    currentStock: $lockedProduct->current_stock,
                    minimumStock: $lockedProduct->minimum_stock,
                );

                DB::afterCommit(function () use ($alertRecipient, $notification): void {
                    Notification::route('mail', $alertRecipient)->notify($notification);
                });
            }

            return $movement;
        });
    }
}
