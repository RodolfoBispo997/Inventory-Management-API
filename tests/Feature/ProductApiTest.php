<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_product_with_zero_stock(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Keyboard',
            'sku' => 'KEY-001',
            'minimum_stock' => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Keyboard')
            ->assertJsonPath('data.current_stock', 0);

        $this->assertDatabaseHas('products', [
            'sku' => 'KEY-001',
            'minimum_stock' => 3,
            'current_stock' => 0,
        ]);
    }

    public function test_lists_and_shows_products_with_pagination(): void
    {
        $product = Product::factory()->create(['name' => 'Monitor', 'sku' => 'MON-001']);

        $this->getJson('/api/products')->assertOk()
            ->assertJsonPath('data.0.sku', 'MON-001')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Monitor');
    }

    public function test_rejects_duplicate_sku(): void
    {
        Product::factory()->create(['sku' => 'DUP-001']);

        $this->postJson('/api/products', ['name' => 'Another', 'sku' => 'DUP-001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sku');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_records_stock_entry_and_updates_product_balance(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'IN',
            'quantity' => 10,
            'reason' => 'Purchase',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'IN')
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.reason', 'Purchase');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 10]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => 10,
            'reason' => 'Purchase',
        ]);

        $this->getJson("/api/products/{$product->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'IN')
            ->assertJsonPath('data.0.quantity', 10)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_records_valid_stock_exit_and_updates_product_balance(): void
    {
        $product = Product::factory()->create(['current_stock' => 8]);

        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'OUT',
            'quantity' => 3,
        ])->assertCreated()->assertJsonPath('data.type', 'OUT');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 5]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'OUT',
            'quantity' => 3,
        ]);
    }

    public function test_rejects_stock_exit_above_balance_without_changes(): void
    {
        $product = Product::factory()->create(['current_stock' => 2]);

        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'OUT',
            'quantity' => 3,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 2]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_rejects_invalid_movement_type_and_non_positive_quantities(): void
    {
        $product = Product::factory()->create();

        foreach ([['type' => 'TRANSFER', 'quantity' => 1], ['type' => 'IN', 'quantity' => 0], ['type' => 'OUT', 'quantity' => -1]] as $payload) {
            $this->postJson("/api/products/{$product->id}/movements", $payload)
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 0]);
    }

    public function test_returns_not_found_for_movements_of_missing_product(): void
    {
        $this->getJson('/api/products/999999/movements')->assertNotFound();
    }
}
