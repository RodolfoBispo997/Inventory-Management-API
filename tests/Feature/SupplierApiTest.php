<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_creates_supplier_and_normalizes_cnpj(): void
    {
        $this->postJson('/api/suppliers', [
            'name' => 'Acme Supplies',
            'cnpj' => '12.345.678/0001-90',
            'email' => 'sales@example.com',
            'phone' => '+55 11 99999-9999',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Acme Supplies')
            ->assertJsonPath('data.cnpj', '12345678000190');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Supplies',
            'cnpj' => '12345678000190',
            'email' => 'sales@example.com',
        ]);
    }

    public function test_creates_supplier_with_unformatted_cnpj(): void
    {
        $this->postJson('/api/suppliers', [
            'name' => 'Northwind Supplies',
            'cnpj' => '98765432000110',
        ])->assertCreated()
            ->assertJsonPath('data.cnpj', '98765432000110');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Northwind Supplies',
            'cnpj' => '98765432000110',
        ]);
    }

    public function test_lists_and_shows_suppliers(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Northwind']);

        $this->getJson('/api/suppliers')->assertOk()
            ->assertJsonPath('data.0.name', 'Northwind')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/suppliers/'.$supplier->id)->assertOk()
            ->assertJsonPath('data.id', $supplier->id)
            ->assertJsonPath('data.cnpj', $supplier->cnpj);
    }

    public function test_rejects_missing_or_invalid_supplier_fields(): void
    {
        $this->postJson('/api/suppliers', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'cnpj']);

        $this->postJson('/api/suppliers', [
            'name' => 'Northwind',
            'cnpj' => '12345',
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj', 'email']);
    }

    public function test_rejects_duplicate_cnpj_even_when_formatted_differently(): void
    {
        Supplier::factory()->create(['cnpj' => '12345678000190']);

        $this->postJson('/api/suppliers', [
            'name' => 'Duplicate Supplier',
            'cnpj' => '12.345.678/0001-90',
        ])->assertUnprocessable()->assertJsonValidationErrors('cnpj');

        $this->assertDatabaseCount('suppliers', 1);
    }
}
