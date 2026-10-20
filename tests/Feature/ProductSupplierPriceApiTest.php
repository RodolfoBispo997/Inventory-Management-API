<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSupplierPriceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_creates_and_updates_supplier_price_without_duplicate_association(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $url = "/api/products/{$product->id}/suppliers/{$supplier->id}";

        $this->putJson($url, ['price' => '14.50', 'supplier_product_code' => 'SUP-ABC'])
            ->assertOk()
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.price', '14.50')
            ->assertJsonPath('data.supplier_product_code', 'SUP-ABC');

        $this->putJson($url, ['price' => '12.25'])
            ->assertOk()
            ->assertJsonPath('data.price', '12.25');

        $this->assertDatabaseCount('product_supplier', 1);
        $this->assertDatabaseHas('product_supplier', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => '12.25',
            'supplier_product_code' => 'SUP-ABC',
        ]);
    }

    public function test_lists_product_suppliers_in_ascending_price_order(): void
    {
        $product = Product::factory()->create();
        $expensive = Supplier::factory()->create(['name' => 'Expensive']);
        $cheap = Supplier::factory()->create(['name' => 'Cheap']);
        $middle = Supplier::factory()->create(['name' => 'Middle']);

        $this->putJson("/api/products/{$product->id}/suppliers/{$expensive->id}", ['price' => '30.00'])->assertOk();
        $this->putJson("/api/products/{$product->id}/suppliers/{$cheap->id}", ['price' => '9.99'])->assertOk();
        $this->putJson("/api/products/{$product->id}/suppliers/{$middle->id}", ['price' => '15.00'])->assertOk();

        $this->getJson("/api/products/{$product->id}/suppliers")
            ->assertOk()
            ->assertJsonPath('data.0.supplier.id', $cheap->id)
            ->assertJsonPath('data.1.supplier.id', $middle->id)
            ->assertJsonPath('data.2.supplier.id', $expensive->id)
            ->assertJsonPath('data.0.price', '9.99')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_rejects_negative_or_over_precision_prices(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $url = "/api/products/{$product->id}/suppliers/{$supplier->id}";

        $this->putJson($url, ['price' => '-0.01'])->assertUnprocessable()
            ->assertJsonValidationErrors('price');

        $this->putJson($url, ['price' => '1.234'])->assertUnprocessable()
            ->assertJsonValidationErrors('price');

        $this->assertDatabaseCount('product_supplier', 0);
    }

    public function test_returns_not_found_for_missing_product_or_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $this->getJson('/api/products/999999/suppliers')->assertNotFound();

        $product = Product::factory()->create();
        $this->putJson("/api/products/{$product->id}/suppliers/999999", ['price' => '1.00'])
            ->assertNotFound();
    }
}
