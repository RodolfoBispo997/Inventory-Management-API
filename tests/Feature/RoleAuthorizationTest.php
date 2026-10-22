<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_is_allowed_by_the_catalog_gate(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $operator = User::factory()->for($organization)->operator()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('manage-inventory-catalog'));
        $this->assertFalse(Gate::forUser($operator)->allows('manage-inventory-catalog'));
    }

    public function test_admin_can_use_all_available_catalog_price_and_movement_operations(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create();
        $product = Product::factory()->for($organization)->create(['current_stock' => 3]);
        $supplier = Supplier::factory()->for($organization)->create();
        $department = Department::factory()->for($organization)->create();
        Sanctum::actingAs($admin);

        $createdProduct = $this->postJson('/api/products', [
            'name' => 'Admin product',
            'sku' => 'ADMIN-001',
        ])->assertCreated();
        $createdSupplier = $this->postJson('/api/suppliers', [
            'name' => 'Admin supplier',
            'cnpj' => '12345678000190',
        ])->assertCreated();
        $createdDepartment = $this->postJson('/api/departments', [
            'name' => 'Admin sector',
            'code' => 'ADMIN',
        ])->assertCreated();

        $this->putJson("/api/products/{$product->id}/suppliers/{$supplier->id}", ['price' => '9.50'])
            ->assertOk();
        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'IN', 'quantity' => 2])
            ->assertCreated();

        $this->getJson('/api/products')->assertOk();
        $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->getJson("/api/products/{$product->id}/movements")->assertOk();
        $this->getJson("/api/products/{$product->id}/suppliers")->assertOk();
        $this->getJson('/api/suppliers')->assertOk();
        $this->getJson("/api/suppliers/{$supplier->id}")->assertOk();
        $this->getJson('/api/departments')->assertOk();
        $this->getJson("/api/departments/{$department->id}")->assertOk();
        $this->getJson('/api/reports/consumption?from=2026-01-01&to=2026-12-31')->assertOk();

        $this->assertDatabaseHas('products', ['sku' => 'ADMIN-001', 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('suppliers', ['cnpj' => '12345678000190', 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('departments', ['code' => 'ADMIN', 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('product_supplier', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => '9.50',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => 2,
        ]);
        $this->assertNotNull($createdProduct->json('data.id'));
        $this->assertNotNull($createdSupplier->json('data.id'));
        $this->assertNotNull($createdDepartment->json('data.id'));
    }

    public function test_operator_can_read_inventory_and_register_stock_movements(): void
    {
        $organization = Organization::factory()->create();
        $operator = User::factory()->for($organization)->operator()->create();
        $product = Product::factory()->for($organization)->create(['current_stock' => 3]);
        $supplier = Supplier::factory()->for($organization)->create();
        $department = Department::factory()->for($organization)->create();
        $product->suppliers()->attach($supplier->id, [
            'organization_id' => $organization->id,
            'price' => '12.00',
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/products')->assertOk();
        $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->getJson("/api/products/{$product->id}/movements")->assertOk();
        $this->getJson("/api/products/{$product->id}/suppliers")->assertOk();
        $this->getJson('/api/suppliers')->assertOk();
        $this->getJson("/api/suppliers/{$supplier->id}")->assertOk();
        $this->getJson('/api/departments')->assertOk();
        $this->getJson("/api/departments/{$department->id}")->assertOk();
        $this->getJson('/api/reports/consumption?from=2026-01-01&to=2026-12-31')->assertOk();
        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'IN',
            'quantity' => 2,
        ])->assertCreated();
        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'OUT',
            'quantity' => 1,
            'department_id' => $department->id,
        ])->assertCreated();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 4]);
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'type' => 'IN',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'type' => 'OUT',
            'quantity' => 1,
            'department_id' => $department->id,
        ]);
    }

    public function test_operator_gets_forbidden_for_catalog_and_price_writes(): void
    {
        $organization = Organization::factory()->create();
        $operator = User::factory()->for($organization)->operator()->create();
        $product = Product::factory()->for($organization)->create();
        $supplier = Supplier::factory()->for($organization)->create();
        $product->suppliers()->attach($supplier->id, [
            'organization_id' => $organization->id,
            'price' => '12.00',
        ]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/products', ['name' => 'Blocked product', 'sku' => 'BLOCKED-001'])
            ->assertForbidden();
        $this->postJson('/api/suppliers', ['name' => 'Blocked supplier', 'cnpj' => '98765432000110'])
            ->assertForbidden();
        $this->postJson('/api/departments', ['name' => 'Blocked sector', 'code' => 'BLOCKED'])
            ->assertForbidden();
        $this->putJson("/api/products/{$product->id}/suppliers/{$supplier->id}", ['price' => '15.00'])
            ->assertForbidden();

        $this->assertDatabaseMissing('products', ['sku' => 'BLOCKED-001']);
        $this->assertDatabaseMissing('suppliers', ['cnpj' => '98765432000110']);
        $this->assertDatabaseMissing('departments', ['code' => 'BLOCKED']);
        $this->assertDatabaseHas('product_supplier', [
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'price' => '12.00',
        ]);
    }

    public function test_protected_routes_return_401_without_authentication(): void
    {
        $this->postJson('/api/products', ['name' => 'Anonymous', 'sku' => 'ANON-001'])
            ->assertUnauthorized();
    }

    public function test_operator_gets_404_for_other_organization_resources_before_role_check(): void
    {
        $ownOrganization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $operator = User::factory()->for($ownOrganization)->operator()->create();
        $ownProduct = Product::factory()->for($ownOrganization)->create();
        $ownSupplier = Supplier::factory()->for($ownOrganization)->create();
        $otherProduct = Product::factory()->for($otherOrganization)->create();
        $otherSupplier = Supplier::factory()->for($otherOrganization)->create();
        Sanctum::actingAs($operator);

        $this->getJson("/api/products/{$otherProduct->id}")->assertNotFound();
        $this->postJson("/api/products/{$otherProduct->id}/movements", ['type' => 'IN', 'quantity' => 1])
            ->assertNotFound();
        $this->putJson("/api/products/{$ownProduct->id}/suppliers/{$otherSupplier->id}", ['price' => '1.00'])
            ->assertNotFound();
        $this->putJson("/api/products/{$otherProduct->id}/suppliers/{$ownSupplier->id}", ['price' => '1.00'])
            ->assertNotFound();
    }

    public function test_registration_does_not_accept_client_selected_roles(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_OPERATOR] as $role) {
            $email = "role-{$role}@example.test";

            $this->postJson('/api/auth/register', [
                'organization_name' => "Organization {$role}",
                'name' => 'Client selected role',
                'email' => $email,
                'password' => 'a-secure-password',
                'role' => $role,
            ])->assertUnprocessable()->assertJsonValidationErrors('role');

            $this->assertDatabaseMissing('users', ['email' => $email]);
            $this->assertDatabaseMissing('organizations', ['name' => "Organization {$role}"]);
        }

        $registered = $this->postJson('/api/auth/register', [
            'organization_name' => 'Server assigned role',
            'name' => 'First Admin',
            'email' => 'server-admin@example.test',
            'password' => 'a-secure-password',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'id' => $registered->json('data.user.id'),
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
