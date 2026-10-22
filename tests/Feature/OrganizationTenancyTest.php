<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = $this->user->organization;
        Sanctum::actingAs($this->user);
    }

    public function test_hides_other_organizations_records_and_rejects_cross_organization_associations(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUser = $otherOrganization->users()->create([
            'name' => 'Other User',
            'email' => 'other@example.test',
            'password' => 'a-secure-password',
        ]);
        $otherProduct = Product::factory()->for($otherOrganization)->create();
        $otherSupplier = Supplier::factory()->for($otherOrganization)->create();
        $otherDepartment = Department::factory()->for($otherOrganization)->create();
        $ownProduct = Product::factory()->for($this->organization)->create();
        $ownSupplier = Supplier::factory()->for($this->organization)->create();

        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/suppliers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/departments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$otherProduct->id}")->assertNotFound();
        $this->getJson('/api/products/not-an-id')->assertNotFound();
        $this->getJson("/api/products/{$otherProduct->id}/movements")->assertNotFound();
        $this->getJson("/api/products/{$otherProduct->id}/suppliers")->assertNotFound();
        $this->getJson("/api/suppliers/{$otherSupplier->id}")->assertNotFound();
        $this->getJson("/api/departments/{$otherDepartment->id}")->assertNotFound();
        $this->putJson("/api/products/{$ownProduct->id}/suppliers/{$otherSupplier->id}", ['price' => '1.00'])
            ->assertNotFound();
        $this->putJson("/api/products/{$otherProduct->id}/suppliers/{$ownSupplier->id}", ['price' => '1.00'])
            ->assertNotFound();
        $this->postJson("/api/products/{$ownProduct->id}/movements", [
            'type' => 'OUT',
            'quantity' => 1,
            'department_id' => $otherDepartment->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('department_id');

        Sanctum::actingAs($otherUser);
        $this->getJson("/api/products/{$ownProduct->id}")->assertNotFound();
    }

    public function test_rejects_client_organization_ids_for_business_writes(): void
    {
        $otherOrganization = Organization::factory()->create();
        $product = Product::factory()->for($this->organization)->create(['current_stock' => 3]);
        $supplier = Supplier::factory()->for($this->organization)->create();

        $this->postJson('/api/products', [
            'name' => 'Forged product',
            'sku' => 'FORGED-001',
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $this->postJson('/api/suppliers', [
            'name' => 'Forged supplier',
            'cnpj' => '12345678000190',
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $this->postJson('/api/departments', [
            'name' => 'Forged department',
            'code' => 'FORGED',
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'IN',
            'quantity' => 1,
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $this->putJson("/api/products/{$product->id}/suppliers/{$supplier->id}", [
            'price' => '1.00',
            'organization_id' => $otherOrganization->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');

        $this->assertDatabaseMissing('products', ['sku' => 'FORGED-001']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'organization_id' => $this->organization->id]);
    }

    public function test_business_codes_are_unique_within_an_organization_and_reusable_across_organizations(): void
    {
        $otherOrganization = Organization::factory()->create();
        $otherUser = $otherOrganization->users()->create([
            'name' => 'Other User',
            'email' => 'codes@example.test',
            'password' => 'a-secure-password',
        ]);

        $this->postJson('/api/products', ['name' => 'Local product', 'sku' => 'SHARED-SKU'])
            ->assertCreated();
        $this->postJson('/api/suppliers', ['name' => 'Local supplier', 'cnpj' => '12345678000190'])
            ->assertCreated();
        $this->postJson('/api/departments', ['name' => 'Local sector', 'code' => 'SHARED'])
            ->assertCreated();

        $this->postJson('/api/products', ['name' => 'Duplicate product', 'sku' => 'SHARED-SKU'])
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->postJson('/api/suppliers', ['name' => 'Duplicate supplier', 'cnpj' => '12345678000190'])
            ->assertUnprocessable()->assertJsonValidationErrors('cnpj');
        $this->postJson('/api/departments', ['name' => 'Duplicate sector', 'code' => 'SHARED'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        Sanctum::actingAs($otherUser);
        $this->postJson('/api/products', ['name' => 'Other product', 'sku' => 'SHARED-SKU'])->assertCreated();
        $this->postJson('/api/suppliers', ['name' => 'Other supplier', 'cnpj' => '12345678000190'])->assertCreated();
        $this->postJson('/api/departments', ['name' => 'Other sector', 'code' => 'SHARED'])->assertCreated();

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('suppliers', 2);
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_consumption_report_excludes_other_organizations_movements(): void
    {
        $otherOrganization = Organization::factory()->create();
        $ownDepartment = Department::factory()->for($this->organization)->create();
        $otherDepartment = Department::factory()->for($otherOrganization)->create();
        $ownProduct = Product::factory()->for($this->organization)->create();
        $otherProduct = Product::factory()->for($otherOrganization)->create();

        $ownProduct->movements()->create([
            'organization_id' => $this->organization->id,
            'department_id' => $ownDepartment->id,
            'type' => 'OUT',
            'quantity' => 2,
        ]);
        $otherProduct->movements()->create([
            'organization_id' => $otherOrganization->id,
            'department_id' => $otherDepartment->id,
            'type' => 'OUT',
            'quantity' => 50,
        ]);

        $this->getJson('/api/reports/consumption?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.departments')
            ->assertJsonPath('data.departments.0.id', $ownDepartment->id)
            ->assertJsonPath('data.departments.0.consumed_quantity', 2);

        $this->getJson('/api/reports/consumption?from=2026-01-01&to=2026-12-31&department_id='.$otherDepartment->id)
            ->assertUnprocessable()->assertJsonValidationErrors('department_id');

        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_registration_requires_an_organization_name_and_never_accepts_organization_id(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'New Owner',
            'email' => 'new-owner@example.test',
            'password' => 'a-secure-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('organization_name');

        $this->postJson('/api/auth/register', [
            'organization_name' => 'New Organization',
            'name' => 'New Owner',
            'email' => 'new-owner@example.test',
            'password' => 'a-secure-password',
            'organization_id' => $this->organization->id,
            'role' => 'super-admin',
        ])->assertUnprocessable()->assertJsonValidationErrors(['organization_id', 'role']);

        $this->assertDatabaseMissing('users', ['email' => 'new-owner@example.test']);
        $this->assertDatabaseMissing('organizations', ['name' => 'New Organization']);
    }
}
